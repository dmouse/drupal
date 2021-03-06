<?php

/**
 * @file
 * Contains \Drupal\user\Controller\UserListController.
 */

namespace Drupal\user\Controller;

use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListController;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists user entities.
 *
 * @see \Drupal\user\Entity\User
 */
class UserListController extends EntityListController implements EntityControllerInterface {

  /**
   * The entity query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * Constructs a new UserListController object.
   *
   * @param string $entity_type
   *   The type of entity to be listed.
   * @param array $entity_info
   *   An array of entity info for the entity type.
   * @param \Drupal\Core\Entity\EntityStorageControllerInterface $storage
   *   The entity storage controller class.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke hooks on.
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The entity query factory.
   */
  public function __construct($entity_type, array $entity_info, EntityStorageControllerInterface $storage, ModuleHandlerInterface $module_handler, QueryFactory $query_factory) {
    parent::__construct($entity_type, $entity_info, $storage, $module_handler);
    $this->queryFactory = $query_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $entity_type,
      $entity_info,
      $container->get('entity.manager')->getStorageController($entity_type),
      $container->get('module_handler'),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_query = $this->queryFactory->get('user');
    $entity_query->condition('uid', 0, '<>');
    $entity_query->pager(50);
    $header = $this->buildHeader();
    $entity_query->tableSort($header);
    $uids = $entity_query->execute();
    return $this->storage->loadMultiple($uids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = array(
      'username' => array(
        'data' => $this->t('Username'),
        'field' => 'name',
        'specifier' => 'name',
      ),
      'status' => array(
        'data' => $this->t('Status'),
        'field' => 'status',
        'specifier' => 'status',
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ),
      'roles' => array(
        'data' => $this->t('Roles'),
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ),
      'member_for' => array(
        'data' => $this->t('Member for'),
        'field' => 'created',
        'specifier' => 'created',
        'sort' => 'desc',
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ),
      'access' => array(
        'data' => $this->t('Last access'),
        'field' => 'access',
        'specifier' => 'access',
        'class' => array(RESPONSIVE_PRIORITY_LOW),
      ),
    );
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['username']['data'] = array(
      '#theme' => 'username',
      '#account' => $entity,
    );
    $row['status'] = $entity->isActive() ? $this->t('active') : $this->t('blocked');

    $roles = array_map('\Drupal\Component\Utility\String::checkPlain', user_role_names(TRUE));
    unset($roles[DRUPAL_AUTHENTICATED_RID]);
    $users_roles = array();
    foreach ($entity->getRoles() as $role) {
      if (isset($roles[$role])) {
        $users_roles[] = $roles[$role];
      }
    }
    asort($users_roles);
    $row['roles']['data'] = array(
      '#theme' => 'item_list',
      '#items' => $users_roles,
    );
    $row['member_for'] = format_interval(REQUEST_TIME - $entity->getCreatedTime());
    $row['access'] = $entity->access ? $this->t('@time ago', array(
      '@time' => format_interval(REQUEST_TIME - $entity->getLastAccessedTime()),
    )) : t('never');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);
    if (isset($operations['edit'])) {
      $destination = drupal_get_destination();
      $operations['edit']['query'] = $destination;
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['accounts'] = parent::render();
    $build['accounts']['#empty'] = $this->t('No people available.');
    $build['pager']['#theme'] = 'pager';
    return $build;
  }

}
