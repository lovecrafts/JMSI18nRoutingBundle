<?php

namespace JMS\I18nRoutingBundle\Tests\Router;

use Symfony\Component\HttpKernel\Kernel;

use JMS\I18nRoutingBundle\Router\DefaultLocaleResolver;
use Symfony\Component\HttpFoundation\Request;

class DefaultLocaleResolverTest extends \PHPUnit_Framework_TestCase
{
    private $resolver;

    /**
     * @dataProvider getResolutionTests
     */
    public function testResolveLocale(Request $request, array $locales, $expected, $message)
    {
        $this->assertSame($expected, $this->resolver->resolveLocale($request, $locales), $message);
    }

    public function getResolutionTests()
    {
        $tests = array();
        $tests[] = array(Request::create('http://host.com/?hl=de'), array('en', 'de', 'fr'), 'en', 'Host has precedence before query parameter');
        $tests[] = array(Request::create('http://host.de'), array('en', 'de', 'fr'), 'de', 'Host defines this locale');
        $tests[] = array(Request::create('http://host.com/fr'), array('en', 'de', 'fr'), 'fr', 'Host/path defines this locale');
        $tests[] = array(Request::create('http://host.com/fr/'), array('en', 'de', 'fr'), 'fr', 'Host/path/ (trailing slash) defines this locale');
        $tests[] = array(Request::create('http://host.com/fridge'), array('en', 'de', 'fr'), 'en', 'Unrelated route should not be considered as locale');
        $tests[] = array(Request::create('/?hl=de'), array('en'), 'de', 'Query parameter is selected');
        $tests[] = array(Request::create('/?hl=de', 'GET', array(), array('hl' => 'en')), array('en'), 'de', 'Query parameter has precedence before cookie');

        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
        $session->expects($this->any())
            ->method('has')
            ->with('_locale')
            ->will($this->returnValue(true));
        $session->expects($this->any())
            ->method('get')
            ->with('_locale')
            ->will($this->returnValue('fr'));
        $session->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('SESS'));

        $tests[] = array($request = Request::create('/?hl=de', 'GET', array(), array('SESS' => 'en')), array('en'), 'de', 'Query parameter has precedence before session');
        $request->setSession($session);

        $tests[] = array($request = Request::create('/', 'GET', array(), array('SESS' => 'en')), array('en'), 'fr', 'Session is used');
        $request->setSession($session);

        $tests[] = array($request = Request::create('/', 'GET', array(), array('hl' => 'es', 'SESS' => 'en')), array('en'), 'fr', 'Session has precedence before cookie.');
        $request->setSession($session);

        $tests[] = array(Request::create('/', 'GET', array(), array('hl' => 'es')), array('en'), 'es', 'Cookie is used');
        $tests[] = array(Request::create('/', 'GET', array(), array('hl' => 'es'), array(), array('HTTP_ACCEPT_LANGUAGE' => 'dk;q=0.5')), array('dk'), 'es', 'Cookie has precedence before Accept-Language header.');
        $tests[] = array(Request::create('/', 'GET', array(), array(), array(), array('HTTP_ACCEPT_LANGUAGE' => 'dk;q=0.5')), array('es', 'dk'), 'dk', 'Accept-Language header is used.');
        $tests[] = array(Request::create('/'), array('fr'), null, 'When Accept-Language header is used, and no locale matches, null is returned');
        $tests[] = array(Request::create('/', 'GET', array(), array(), array(), array('HTTP_ACCEPT_LANGUAGE' => '')), array('en'), null, 'Returns null if no method could be used');

        return $tests;
    }

    protected function setUp()
    {
        $this->resolver = new DefaultLocaleResolver('hl', array(
            'fr' => array('host' => 'host.com', 'path' => '/fr/'),
            'en' => array('host' => 'host.com', 'path' => '/'),
            'de' => array('host' => 'host.de', 'path' => '/'),
        ));
    }
}