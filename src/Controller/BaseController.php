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
use Doctrine\Persistence\ManagerRegistry;

#[IsGranted('ROLE_USER')]
final class BaseController extends AbstractController
{
    #[Route('/categorias', name: 'categorias')]
    public function mostrar_categorias(EntityManagerInterface $em): Response
    {
        $categorias = $em->getRepository(Categoria::class)->findAll();
        return $this->render('categorias/mostrar_categorias.html.twig', [
            'categorias' => $categorias,
        ]);
    }
    
    #[Route('/productos/{categoria_id}', name: 'productos')]
    public function mostrar_productos(EntityManagerInterface $em, int $categoria_id): Response
    {
        $objeto_categoria = $em->getRepository(Categoria::class)->find($categoria_id);
        $productos = $objeto_categoria->getProductos();
        return $this->render('productos/mostrar_productos.html.twig', [
            'productos' => $productos,
        ]);
    }
    
    #[Route('/anadir', name: 'anadir')]
    public function anadir_productos(EntityManagerInterface $em, Request $request, CestaCompra $cesta){
        // Recogemos los datos de entrada (los valores de la petición post)
        $productos_ids = $request->request->get("productos_id");
        $unidades = $request->request->get("unidades");
        
        // Obtenemos un array de objetos Producto, a partir de sus id
        $productos = $em->getRepository(Producto::class)->findProductosByIds($productos_ids);
        
        // Llamamos a carga_productos para añadir a la cesta los productos 
        // seleccionados junto con sus unidades
        $cesta->cargar_productos($productos, $unidades);
        
        $objetos_producto = array_values($productos);
        
        return $this->redirectToRoute('productos', ['categoria'=>$objetos_producto(0)->getCategoria()]);

    }
    
    #[Route ('/cesta', name: 'cesta')]
    public function cesta(CestaCompra $cesta) {
        
        return $this->render('cesta/mostrar_cesta.html.twig', [
            'productos' => $cesta->get_productos(),
            'unidades' => $cesta->get_unidades()
        ]);
    }
}
// Comando de prueba para subir los cambios
