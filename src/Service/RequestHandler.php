<?php

namespace Drupal\protect_before_launch\Service;

use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Class RequestHandler.
 *
 * @package Drupal\protect_before_launch
 */
class RequestHandler implements HttpKernelInterface {


  /**
   * Protected config.
   *
   * @var \Drupal\protect_before_launch\Service\Configuration
   */
  protected $config = NULL;

  /**
   * Protected httpKernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel = NULL;

  /**
   * RequestHandler constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel
   *   Public function httpKernel.
   * @param \Drupal\protect_before_launch\Service\Configuration $config
   *   Public function config.
   */
  public function __construct(HttpKernelInterface $httpKernel, Configuration $config) {
    $this->httpKernel = $httpKernel;
    $this->config = $config;
  }

  /**
   * Shield pages is enabled status.
   *
   * @return bool
   *   Protected function shieldPage bool.
   */
  protected function shieldPage() {
    return $this->config->getProtect() ? TRUE : FALSE;
  }

  /**
   * Check if path is excluded from password protection.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Protected function excludedPath request.
   *
   * @return bool
   *   Protected excludedPath bool.
   */
  protected function excludedPath(Request $request) {
    $currentPath = urldecode($request->getRequestUri());

    foreach ($this->config->getExcludePaths() as $path) {
      if (strlen(trim($path)) && preg_match('/' . str_replace('/', '\/', $path) . '/i', $currentPath)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Is user allowed to visit page if not display password.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Protected function isAllowed request.
   * @param \Drupal\Core\Render\HtmlResponse $response
   *   Protected function isAllowed response.
   *
   * @return \Drupal\Core\Render\HtmlResponse
   *   Protected function isAllowed.
   */
  protected function isAllowed(Request $request, HtmlResponse $response) {
    if ($this->shieldPage() && !$this->excludedPath($request) && !$this->config->validate($request->getUser(), $request->getPassword())) {
      $response->headers->add(['WWW-Authenticate' => 'Basic realm="' . $this->config->getRealm() . '"']);
      $response->setStatusCode(401, 'Unauthorized');
      $response->setContent($this->config->getContent());
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    /** @var \Drupal\Core\Render\HtmlResponse $response */
    $response = $this->httpKernel->handle($request, $type, $catch);
    if ('cli' != php_sapi_name() && get_class($response) == 'Drupal\Core\Render\HtmlResponse') {
      $response = $this->isAllowed($request, $response);
    }
    return $response;
  }

}
