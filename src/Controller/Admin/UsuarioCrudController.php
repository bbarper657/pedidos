<?php

namespace App\Controller\Admin;

use App\Entity\Usuario;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\PasswordHasher\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use Doctrine\ORM\EntityManagerInterface;

class UsuarioCrudController extends AbstractCrudController
{
    // Propiedad para guardar el servicio
    private $userPasswordHasher;
    
    // Inicializamos el PasswordHasher
    public function __construct(UserPasswordHasherInterface $userPasswordHasher) {
        $this->userPasswordHasher = $userPasswordHasher;
    }
    
    // Este metodo indica al crud que trabaja con la entidad usuario
    public static function getEntityFqcn(): string {
        return Usuario::class;
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entity): void
    {
        // Decide si hay que hasear la contraseña
        $this->hashPassWord($entity);
        // Delegamos el guardado real a EasyAdmin
        parent::updateEntity($entityManager, $entity);
    }
    
    
    public function persistEntity(EntityManagerInterface $entityManager, $entity): void {
        // Comprobamos si hay contraseña y la hasheamos
        $this->hashPassWord($entity);
        // EasyAdmin se encarga del persist + flush
        parent::persistEntity($entityManager, $entity);
    }
    
    // Método donde se decide si una contraseña debe hashearse o no
    public function hashPassWord($entity) {
        
        // Comprobamos si se trata de una entidad Usuario para continuar
        if (!$entity instanceof Usuario) {
            return;
        }
        
        // Comprobamos si el usuario a añadido una contraseña nueva
        if (!$entity->getPlainPassword()) {
            return;
        }
        
        // Si llegamos aqui significa que hay una contraseña en texto claro y es un usuario
        // Transformamor el texto claro en un hash
        $entity->setPassword(
            $this->userPasswordHasher->hashPassword(
                $entity, 
                $entity->getPlainPassword()));
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
