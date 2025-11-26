<?php

namespace App\Services;

use App\Entity\Producto;
use Symfony\Component\HttpFoundation\RequestStack;

class CestaCompra {
    
    protected $requestStack;
    protected $productos;
    protected $unidades;

    public function __construct(RequestStack $requestStack) {
        $this->requestStack = $requestStack;
    }
    
    public function cargar_productos($productos, $unidades) {
        for ($i = 0; $i < count($productos); $i++) {
            if ($unidades[i] != 0) {
                
                // Cargamos un producto a la sesión
                $this->cargar_producto($productos[$i], $unidades[$i]);
            }
        }
    }
    
    // Recibe como parámetro el objeto Producto con su unidad y la carga a la cesta
    public function cargar_producto($producto, $unidad) {
        $this->carga_cesta(); // Cargamos la sesión de la cesta
        
        // Ahora podemos utilizar los productos y las unidades
        // Creamos una variable donde guardamos el codigo del producto
        $codigo_producto = $producto->getCodigo();
        
        // Cargamos el código de producto a la cesta
        // Si el producto ya existe, incrementamos las unidades de la cesta
        if (array_key_exists($codigo_producto, $this->productos)){
            
            // Guardamos un array de todos los codigos de los productos
            $codigos_productos = array_keys($this->productos);
            
            
            $posicion = array_search($codigo_producto, $codigos_productos);
            
            $unidades[$posicion]+=$unidad;
            
        } else {
            $productos[] = ['$codigo_producto' => $producto];
            $unidades[] = [$unidad];
        }
        
        $this->guardar_cesta();
    }
    
    protected function carga_cesta() {
        // Guardamos la sesion
        $sesion = $this->requestStack->getSession();
        
        // Si hay productos en la sesión, los cargamos en los atributos del objeto cesta
        if($sesion->has("productos") && $sesion->has("unidades")) {
            $this->productos = $sesion->get("productos");
            $this->unidades = $sesion->get("unidades");
        } else {
            $this->productos = [];
            $this->unidades = [];
        }
        
        // Creamos un carrito
        $carrito = $sesion->get('carrito');
    }
    
    protected function guardar_cesta() {
        $sesion = $this->requestStack->getSession();
        
        $sesion->set($this->productos);
        $sesion->set($this->unidades);
    }
    
    public function get_productos() {
        $this->carga_cesta();
        return $this->productos;
    }
    
    public function get_unidades() {
        $this->carga_cesta();
        return $this->unidades;
    }
}

