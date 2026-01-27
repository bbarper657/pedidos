<?php

namespace App\Services;

use App\Entity\Producto;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CestaCompra {
    
    protected $requestStack;
    protected $productos;
    protected $unidades;

    public function __construct(RequestStack $requestStack) {
        $this->requestStack = $requestStack;
    }
    
    public function cargar_productos($productos, $unidades) {
        // Recibe como parámetros los productos y las unidades añadidos del formulario
        $this->carga_cesta(); // Cargamos la sesión de la cesta
        
        for ($i = 0; $i < count($productos); $i++) {
            if ($unidades[$i] != 0) {
                
                // Cargamos un producto a la sesión
                $this->cargar_producto($productos[$i], $unidades[$i]);
            }
        }
        // Guardamos en la cesta
        $this->guardar_cesta();
    }
    
    // Recibe como parámetro el objeto Producto con su unidad y la carga a la cesta
    public function cargar_producto($producto, $unidad) {
        
        // Ahora podemos utilizar los productos y las unidades
        // Creamos una variable donde guardamos el codigo del producto
        $codigo_producto = $producto->getCodigo();
        
        // Cargamos el código de producto a la cesta
        // Mira si el código existe
        // Si el producto ya existe, incrementamos las unidades de la cesta
        if (array_key_exists($codigo_producto, $this->productos)){
            
            $this->unidades[$codigo_producto]+=$unidad;
            
        } elseif ($unidad != 0){
            $this->productos[$codigo_producto] = $producto;
            $this->unidades[$codigo_producto] = $unidad;
        }
    }
    
    // Recupera el array de productos y unidades de la sesion
    protected function carga_cesta() {
        // Recuperamos la sesion
        $sesion = $this->requestStack->getSession();
        
        // Si hay productos en la sesión, los cargamos en los atributos del objeto cesta
        if($sesion->has("productos") && $sesion->has("unidades")) {
            $this->productos = $sesion->get("productos");
            $this->unidades = $sesion->get("unidades");
        } else {
            $this->productos = [];
            $this->unidades = [];
        }
    }
    
    protected function guardar_cesta() {
        $sesion = $this->requestStack->getSession();
        
        $sesion->set('productos', $this->productos);
        $sesion->set('unidades', $this->unidades);
    }
    
    public function get_productos() {
        $this->carga_cesta();
        return $this->productos;
    }
    
    public function get_unidades() {
        $this->carga_cesta();
        return $this->unidades;
    }
    
    public function eliminar_producto($codigo_producto, $unidades) {
        // Cargar la sesion de la cesta
        $this->carga_cesta();
        
        if (array_key_exists($codigo_producto, $this->productos)) {
            $this->unidades[$codigo_producto] = $this->unidades[$codigo_producto] - $unidades;
            // Si al decrementar se queda sin unidades el producto, se elimina de la cesta
            if ($this->unidades[$codigo_producto] <= 0) {
                unset($this->unidades[$codigo_producto]);
                unset($this->productos[$codigo_producto]);
            }
            $this->guardar_cesta();
        }
    }
    
    public function calcular_coste() {
        $resultado = 0;
        foreach ($this->productos as $codigo_producto => $producto) {
            $resultado += $producto -> getPrecio() * $this->unidades[$codigo_producto];
        }
        return $resultado;
    }
}

