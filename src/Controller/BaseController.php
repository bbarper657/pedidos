<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Categoria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Producto;
use Symfony\Component\HttpFoundation\Request;
use App\Services\CestaCompra;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use App\Entity\Pedido;
use App\Entity\PedidoProducto;
use App\Entity\Usuario;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[IsGranted('ROLE_USER')]
final class BaseController extends AbstractController {

    #[Route('/categorias', name: 'categorias')]
    public function mostrar_categorias(EntityManagerInterface $em): Response {
        $categorias = $em->getRepository(Categoria::class)->findAll();
        return $this->render('categorias/mostrar_categorias.html.twig', [
                    'categorias' => $categorias,
        ]);
    }

    #[Route('/productos/{categoria_id}', name: 'productos')]
    public function mostrar_productos(EntityManagerInterface $em, int $categoria_id): Response {
        $objeto_categoria = $em->getRepository(Categoria::class)->find($categoria_id);
        $productos = $objeto_categoria->getProductos();
        return $this->render('productos/mostrar_productos.html.twig', [
                    'productos' => $productos,
        ]);
    }

    #[Route('/anadir', name: 'anadir')]
    public function anadir_productos(EntityManagerInterface $em, Request $request, CestaCompra $cesta): Response {
        // Recogemos los datos de entrada (los valores de la petición post)
        $productos_ids = $request->request->all("productos_id");
        $unidades = $request->request->all("unidades");

        // Obtenemos un array de objetos Producto, a partir de sus id
        $productos = $em->getRepository(Producto::class)->findBy(['id' => $productos_ids]);

        // Llamamos a carga_productos para añadir a la cesta los productos 
        // seleccionados junto con sus unidades
        $cesta->cargar_productos($productos, $unidades);

        // Obtenemos el id de la categoria a partir de cualquier objeto Producto
        $objetos_producto = array_values($productos);

        $categoria_id = $objetos_producto[0]->getCategoria()->getId();

        return $this->redirectToRoute('productos', [
                    'categoria_id' => $categoria_id
        ]);
    }

    #[Route('/cesta', name: 'cesta')]
    public function cesta(CestaCompra $cesta) {

        return $this->render('cesta/mostrar_cesta.html.twig', [
                    'productos' => $cesta->get_productos(),
                    'unidades' => $cesta->get_unidades()
        ]);
    }

    #[Route('/eliminar', name: 'eliminar')]
    public function eliminar(Request $request, CestaCompra $cesta) {

        // Recogemos los datos de entrada (los valores de la petición post)
        $producto_id = $request->request->get("producto_id");
        $unidades = $request->request->get("unidades");

        $cesta->eliminar_producto($producto_id, $unidades);

        return $this->redirectToRoute('cesta');
    }

    #[Route('/pedido', name: 'pedido')]
    public function pedido(EntityManagerInterface $em, CestaCompra $cesta, MailerInterface $mailer) {
        // Iniciamos la variable error
        $error = 0;

        $productos = $cesta->get_productos();
        $unidades = $cesta->get_unidades();

        if (count($productos) == 0) {
            // Valor 1 cuando no hay productos en la cesta
            $error = 1;
        } else {
            // Creamos un objeto pedido con sus setter
            $pedido = new Pedido();
            // Calculamos el coste del pedido
            $pedido->setCoste($cesta->calcular_coste());
            // Hacemos un objeto nuevo para poder conseguir la hora actual
            $pedido->setFecha(new \DateTime());
            // Le damos el usuario del pedido
            $pedido->setUsuario($this->getUser());

            // Permanece en espera con ese pedido
            $em->persist($pedido);

            foreach ($productos as $codigo_producto => $productoCesta) {
                $pedidoProducto = new PedidoProducto();
                $pedidoProducto->setPedido($pedido);

                $producto = $em->getRepository(Producto::class)->find(['id' => $productoCesta->getId()]);

                $pedidoProducto->setProducto($producto);
                $pedidoProducto->setUnidades($unidades[$codigo_producto]);

                // Le cargamos al pedidoProducto
                $em->persist($pedidoProducto);
            }

            try {
                // EL flush hace que se guarde en la base
                // Y genera una sesion
                $em->flush();
            } catch (Exception $ex) {
                // Este error será porque falla el acceso a la BD
                $error = 2;
            }

            // Servicio mailer
            if (!$error) {
                // Obtenemos el id del usuario de la sesion
                $usuario_id = $this->getUser()->getId();
                // Sacamos el usuario del id
                $usuario = $em->getRepository(Usuario::class)->find($usuario_id);

                $destination_email = $usuario->getEmail();

                $email = (new TemplatedEmail())
                        ->from('bbarper657@g.educaand.es')
                        ->to(new Address($destination_email))
                        ->subject('Confirmación de pedido' . $pedido->getId())

                        // indicamos la ruta de la plantilla
                        ->htmlTemplate('correo.html.twig')
                        ->locale('es')
                        // pasamos variables (clave => valor) a la plantilla
                        ->context([
                            'pedido_id' => $pedido->getId(), 'productos' => $cesta->get_productos(), 'unidades' => $cesta->get_unidades(),
                            'coste' => $cesta->calcular_coste(),
                ]);
                $mailer->send($email);
            }
        }

        return $this->render('pedido/pedido.html.twig', [
                    'pedido_id' => $pedido->getId(),
                    'error' => $error,
        ]);
    }

    #[Route('/historial', name: 'historial')]
    public function historial(EntityManagerInterface $em): Response {
        // Recuperamos el usuario logueado
        $usuario = $this->getUser();

        // Accedemos a todos los pedidos del usuario
        $pedidos = $em->getRepository(Pedido::class)->findBy(
                ['usuario' => $usuario],
                ['fecha' => 'DESC']
        );

        // Creamos un array donde guardaremos todos los pedidos
        $pedidoProductosPorPedido = [];

        // Recorremos cada pedido del usuario
        foreach ($pedidos as $pedido) {
            // Buscamos en la base de datos cada pedido del usuario
            $pedidoProductosPorPedido[$pedido->getId()] = $em->getRepository(PedidoProducto::class)
                    ->findBy(['pedido' => $pedido]);
        }

        // Devolvemos a la vista los datos adquiridos
        return $this->render('historial/mostrar_historial.html.twig', [
                    'pedidos' => $pedidos,
                    'pedidoProductos' => $pedidoProductosPorPedido
        ]);
    }
    
    #[Route('/acceso-denegado', name: 'acceso-denegado')]
    public function accesoDenegado() {
        return $this->render('acceso/acceso_denegado.html.twig');
    }
}

// Comando de prueba para subir los cambios
