<?php

namespace Drupal\decoupled_router;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Path translation event.
 */
class PathTranslatorEvent extends GetResponseEvent {

  const TRANSLATE = 'decoupled_router.translate_path';

  /**
   * The path that needs translation.
   *
   * @var string
   */
  protected $path;

  /**
   * The redirect entity, if any.
   *
   * @var \Drupal\redirect\Entity\Redirect
   */
  protected $redirect;

  /**
   * PathTranslatorEvent constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $kernel
   *   The kernel.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param int $requestType
   *   The type of request: master or subrequest.
   * @param string $path
   *   The path to process.
   * @param \Drupal\redirect\Entity\Redirect $redirect
   *   Indicates if the current path is a redirect
   */
  public function __construct(HttpKernelInterface $kernel, Request $request, $requestType, $path, $redirect = NULL) {
    parent::__construct($kernel, $request, $requestType);
    $this->path = $path;
    $this->redirect = $redirect;
  }

  /**
   * Get the path.
   *
   * @return string
   *   The path.
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * Set the path.
   *
   * @param string $path
   *   The path.
   */
  public function setPath($path) {
    $this->path = $path;
  }

  /**
   * Get the redirect from the current path.
   *
   * @return \Drupal\redirect\Entity\Redirect
   *   The redirect entity if it comes from a redirection. NULL otherwise.
   */
  public function getRedirect() {
    return $this->redirect;
  }

  /**
   * Set the redirect for the current path.
   *
   * @param \Drupal\redirect\Entity\Redirect|NULL $redirect
   *   The redirect entity if it comes from a redirection.
   */
  public function setRedirect($redirect) {
    $this->redirect = $redirect;
  }

}
