<?php

namespace Drupal\Tests\decoupled_router\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Language\Language;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\redirect\Entity\Redirect;
use Drupal\Tests\BrowserTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group decoupled_router
 */
class DecoupledRouterFunctionalTest extends BrowserTestBase {

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes = [];

  public static $modules = [
    'language',
    'node',
    'path',
    'decoupled_router',
    'redirect',
    'jsonapi',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $language = ConfigurableLanguage::createFromLangcode('ca');
    $language->save();

    // In order to reflect the changes for a multilingual site in the container
    // we have to rebuild it.
    $this->rebuildContainer();

    \Drupal::configFactory()->getEditable('language.negotiation')
      ->set('url.prefixes.ca', 'ca')
      ->save();
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->user = $this->drupalCreateUser([
      'access content',
      'create article content',
      'edit any article content',
      'delete any article content',
    ]);
    $this->createDefaultContent(3);
    $redirect = Redirect::create(['type' => '301']);
    $redirect->setSource('/foo');
    $redirect->setRedirect('/node--0');
    $redirect->setLanguage(Language::LANGCODE_NOT_SPECIFIED);
    $redirect->save();
    $redirect = Redirect::create(['type' => '301']);
    $redirect->setSource('/bar');
    $redirect->setRedirect('/foo');
    $redirect->setLanguage(Language::LANGCODE_NOT_SPECIFIED);
    $redirect->save();
    $redirect = Redirect::create(['type' => '301']);
    $redirect->setSource('/foo--ca');
    $redirect->setRedirect('/node--0--ca');
    $redirect->setLanguage('ca');
    $redirect->save();
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Creates default content to test the API.
   *
   * @param int $num_articles
   *   Number of articles to create.
   */
  protected function createDefaultContent($num_articles) {
    $random = $this->getRandomGenerator();
    for ($created_nodes = 0; $created_nodes < $num_articles; $created_nodes++) {
      $values = [
        'uid' => ['target_id' => $this->user->id()],
        'type' => 'article',
        'path' => '/node--' . $created_nodes,
        'title' => $random->name(),
      ];
      $node = $this->createNode($values);
      $values['title'] = $node->getTitle() . ' (ca)';
      $values['field_image']['alt'] = 'alt text (ca)';
      $values['path'] = '/node--' . $created_nodes . '--ca';
      $node->addTranslation('ca', $values);

      $node->save();
      $this->nodes[] = $node;
    }
  }

  /**
   * Tests reading multilingual content.
   */
  public function testNegotiationNoMultilingual() {
    // This is not build with data providers to avoid rebuilding the environment
    // each test.
    $make_assertions = function ($path, DecoupledRouterFunctionalTest $test) {
      $res = $test->drupalGet(
        Url::fromRoute('decoupled_router.path_translation'),
        ['query' => ['path' => Url::fromUserInput($path)->toString()]]
      );
      $test->assertSession()->statusCodeEquals(200);
      $output = Json::decode($res);
      $test->assertStringEndsWith('/node--0', $output['resolved']);
      $test->assertSame($test->nodes[0]->id(), $output['entity']['id']);
      $test->assertSame('node--article', $output['jsonapi']['resourceName']);
      $test->assertStringEndsWith('/jsonapi/node/article/' . $test->nodes[0]->uuid(), $output['jsonapi']['individual']);
    };
    list(,, $base_path) = $this->introspectPath();
    // Test cases:
    $test_cases = [
      // 1. Test negotiation by system path for /node/1 -> /node--0.
      $base_path . 'node/1',
      // 2. Test negotiation by alias for /node--0.
      $base_path . 'node--0',
      // 3. Test negotiation by multiple redirects for /bar -> /foo -> /node--0.
      $base_path . 'bar',
    ];
    array_walk($test_cases, function ($test_case) use ($make_assertions) {
      $make_assertions($test_case, $this);
    });
  }

  /**
   * Introspects the globals to get information about the base path.
   *
   * @return array
   *   The base_root, the base_url and the base_path.
   */
  protected function introspectPath() {
    $request = Request::createFromGlobals();
    // Create base URL.
    $base_root = $request->getSchemeAndHttpHost();
    $base_url = $base_root;

    // For a request URI of '/index.php/foo', $_SERVER['SCRIPT_NAME'] is
    // '/index.php', whereas $_SERVER['PHP_SELF'] is '/index.php/foo'.
    if ($dir = rtrim(dirname($request->server->get('SCRIPT_NAME')), '\/')) {
      // Remove "core" directory if present, allowing install.php,
      // authorize.php, and others to auto-detect a base path.
      $core_position = strrpos($dir, '/core');
      if ($core_position !== FALSE && strlen($dir) - 5 == $core_position) {
        $base_path = substr($dir, 0, $core_position);
      }
      else {
        $base_path = $dir;
      }
      $base_url .= $base_path;
      $base_path .= '/';
    }
    else {
      $base_path = '/';
    }

    return [$base_root, $base_url, $base_path];
  }

}
