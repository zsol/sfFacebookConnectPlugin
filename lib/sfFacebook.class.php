<?php

/**
 *
 * @package    sfFacebookConnectPlugin
 * @author     Fabrice Bernhard
 *
 */
class sfFacebook
{
  protected static
    $client          = null; // Facebook instance
  protected static
    $guard_adapter   = null;
  protected static
    $is_js_loaded       = false;

  public static function getFacebookCookie()
  {
    $app_id = self::getApiKey();
    $application_secret = self::getApiSecret();
    $args = array();
    if (!isset($_COOKIE['fbs_' . $app_id]))
    {
      return null;
    }
    parse_str(trim($_COOKIE['fbs_' . $app_id], '\\"'), $args);
    ksort($args);
    $payload = '';
    foreach ($args as $key => $value)
    {
      if ($key != 'sig')
      {
        $payload .= $key . '=' . $value;
      }
    }
    if (md5($payload . $application_secret) != $args['sig'])
    {
      return null;
    }
    return $args;
  }

  /**
   * gets the facebook client instance
   *
   * @return Facebook
   * @author fabriceb
   * @since 2009-05-17
   * @since 2010-05-12 Benjamin Grandfond <benjaming@theodo.fr>: new Facebook php-sdk
   * @since 2010-09-03 Benjamin Grandfond : correct the parameters sent to the facebook class constructor
   */
  public static function getFacebookClient()
  {
    if (self::$client === null)
    {
      $params = array(
        'appId'  => self::getApiId(),
        'secret' => self::getApiSecret(),
        'cookie' => self::getApiCookie(),
        'domain' => self::getApiDomain()
      );

      self::$client = new Facebook($params);
    }

    if (!self::$client)
    {
      error_log('Could not create facebook client.');
    }

    return self::$client;
  }

  /**
   * get the facebook session
   *
   * @return Array
   * @author Benjamin Grandfond <benjaming@theodo.fr>
   * @since 2010-05-13
   */
  public static function getSession()
  {

    return self::getFacebookClient()->getSession();
  }

  /**
   * get the facebook user
   *
   * @return Array
   * @author Benjamin Grandfond <benjaming@theodo.fr>
   * @since 2010-05-13
   */
  public static function getUser()
  {

    return self::getFacebookClient()->getUser();
  }

  /**
   * get datas through the faceook graph api
   * @see http://developers.facebook.com/docs/api
   *
   * @return Array
   * @author Benjamin Grandfond <benjaming@theodo.fr>
   * @since 2010-05-13
   */
  public static function getFacebookApi($param)
  {

    return self::getFacebookClient()->api($param);
  }

  /**
   * gets the facebook api key
   *
   * @return Facebook
   * @author fabriceb
   * @since 2009-05-17
   */
  public static function getApiKey()
  {

    return sfConfig::get('app_facebook_api_key');
  }

  /**
   * gets the facebook api secret
   *
   * @return Facebook
   * @author fabriceb
   * @since 2009-05-17
   */
  public static function getApiSecret()
  {

    return sfConfig::get('app_facebook_api_secret');
  }

  /**
   * get the facebook app id
   *
   * @return integer
   * @author Benjamin Grandfond <benjaming@theodo.fr>
   * @since 2010-09-03
   */
  public static function getApiId()
  {

    return sfConfig::get('app_facebook_api_id');
  }

  /**
   * get the facebook app cookie support
   *
   * @return Boolean
   * @author Benjamin Grandfond <benjaming@theodo.fr>
   * @since 2010-05-12
   */
  public static function getApiCookie()
  {

    return sfConfig::get('app_facebook_api_cookie', true);
  }

  /**
   * get the facebook app domain
   *
   * @return Boolean
   * @author Benjamin Grandfond <benjaming@theodo.fr>
   * @since 2010-05-12
   */
  public static function getApiDomain()
  {

    return sfConfig::get('app_facebook_api_domain', null);
  }

  /**
   * gets or create user with facebook uid inprofile
   *
   * @param Integer $facebook_uid
   * @param boolean $isActive
   * @return sfGuardUser $sfGuardUser
   */
  public static function getOrCreateUserByFacebookUid($facebook_uid, $isActive = true)
  {
    $sfGuardUser = self::getGuardAdapter()->getSfGuardUserByFacebookUid($facebook_uid, $isActive);

    if (!$sfGuardUser instanceof sfGuardUser)
    {
      if (sfConfig::get('sf_logging_enabled'))
      {
        sfContext::getInstance()->getLogger()->info('{sfFacebookConnect} No user exists with current email hash');
      }
      $sfGuardUser = self::getGuardAdapter()->createSfGuardUserWithFacebookUid($facebook_uid);
    }

    return $sfGuardUser;
  }

  /**
   * gets user with facebook uid inprofile
   *
   * @param Integer $facebook_uid
   * @param boolean $isActive
   * @return sfGuardUser $sfGuardUser
   */
  public static function getUserByFacebookUid($facebook_uid, $isActive = true)
  {
    $sfGuardUser = self::getGuardAdapter()->retrieveSfGuardUserByFacebookUid($facebook_uid, $isActive);

    if (!$sfGuardUser instanceof sfGuardUser)
    {
      if (sfConfig::get('sf_logging_enabled'))
      {
        sfContext::getInstance()->getLogger()->info('{sfFacebookConnect} No user exists with current email hash');
      }
    }

    return $sfGuardUser;
  }

  /**
   * Gets the currently logged sfGuardUser using Facebook Session
   *
   * @param boolean $create whether to automatically create a sfGuardUser
   * if none found corresponding to the Facebook session
   * @param boolean $isActive
   * @return sfGuardUser
   * @author fabriceb
   * @since 2009-05-17
   * @since 2009-08-25
   */
  public static function getSfGuardUserByFacebookSession($create = true, $isActive = true)
  {
    // We get the facebook uid from session
    $fb_uid = self::getAnyFacebookUid();
    if ($fb_uid)
    {

      if ($create)
      {

        return self::getOrCreateUserByFacebookUid($fb_uid, $isActive);
      }
      else
      {

        return self::getUserByFacebookUid($fb_uid, $isActive);
      }
    }

    if (sfConfig::get('sf_logging_enabled'))
    {
      sfContext::getInstance()->getLogger()->info('{sfFacebookConnect} No current Facebook session');
    }

    return null;
  }

  /**
   * checks if it's an FBML application
   * this is an alias for inCanvas()
   *
   * @return boolean
   * @author fabriceb
   * @since Jun 8, 2009 fabriceb
   */
  public static function isInsideFacebook()
  {

    return self::inCanvas();
  }

  /**
   * checks if it's an FBML application
   *
   * @return boolean
   * @author fabriceb
   * @since Jun 8, 2009 fabriceb
   */
  public static function inCanvas()
  {
    return sfContext::getInstance()->getRequest()->getParameterHolder()->get('fb_sig_in_canvas', false);
  }

  /**
   * checks if it's an iframe application
   *
   * @return boolean
   * @author fabriceb
   * @since Jun 8, 2009 fabriceb
   */
  public static function inIframe()
  {
    return sfContext::getInstance()->getRequest()->getParameterHolder()->get('fb_sig_in_iframe', false);
  }

  /**
   * redirects to the login page of the Facebook application if not logged yet
   *
   * @author fabriceb
   * @since Jun 8, 2009 fabriceb
   */
  public static function requireLogin()
  {
    self::getFacebookClient()->require_login();
  }

  /**
   * redirects depnding on in canvas or not
   *
   * @param $url
   * @param $statusCode
   * @return mixed sfView::NONE or sfStopException
   * @author fabriceb
   * @since Jun 8, 2009 fabriceb
   */
  public static function redirect($url, $statusCode = 302)
  {

    $context = sfContext::getInstance();
    $response = $context->getResponse();

    if (self::inCanvas())
    {
      $url = sfConfig::get('app_facebook_app_url').$context->getController()->genUrl($url, false);

      $text = '<fb:redirect url="' . $url . '"/>';
      $response->setContent(sfContext::getInstance()->getResponse()->getContent().$text);

      return sfView::NONE;
    }
    else
    {
      $fb_parameters = '?'.sfFacebook::getFacebookSigParameters(sfContext::getInstance()->getRequest());
      $url = $context->getController()->genUrl($url, true).$fb_parameters;

      $response->clearHttpHeaders();
      $response->setStatusCode($statusCode);
      $response->setHttpHeader('Location', $url);
      $response->setContent(sprintf('<html><head><meta http-equiv="refresh" content="%d;url=%s"/></head></html>', 0, htmlspecialchars($url, ENT_QUOTES, sfConfig::get('sf_charset'))));
      $response->send();

      throw new sfStopException();
    }
  }

  /**
   *
   * @param integer $user_uid
   * @return integer[]
   * @author fabriceb
   * @since Jun 9, 2009 fabriceb
   */
  public static function getFacebookFriendsUids($user_uid = null)
  {

    try
    {
      $friends_uids = self::getFacebookApi()->friends_get(null, $user_uid);
    }
    catch(FacebookRestClientException $e)
    {
      $friends_uids = array();
      if (sfConfig::get('sf_logging_enabled'))
      {
        sfContext::getInstance()->getLogger()->info('{FacebookRestClientException} '.$e->getMessage());
      }
    }

    return $friends_uids;
  }

  /**
  *
  * @return sfFacebookGuardAdapter
  * @author fabriceb
  * @since Aug 10, 2009
  * @since 2009-10-08 Alban Creton : added configurability of the Guard Adapter.
  */
  public static function getGuardAdapter()
  {
    if (self::$guard_adapter === null)
    {
      if(sfConfig::get('app_facebook_guard_adapter') && class_exists(sfConfig::get('app_facebook_guard_adapter'), true))
      {
        $class = sfConfig::get('app_facebook_guard_adapter');
      }
      else if (class_exists('sfGuardUserPeer', true))
      {
        $class = 'sfFacebookPropelGuardAdapter';
      }
      else
      {
        $class = 'sfFacebookDoctrineGuardAdapter';
      }
      self::$guard_adapter = new $class();
    }
    if (!self::$guard_adapter)
    {
      error_log('Could not create guard adapter.');
    }

    return self::$guard_adapter;
  }


  /**
   *
   * @return boolean
   * @author fabriceb
   * @since Aug 27, 2009
   */
  public static function isJsLoaded()
  {

    return self::$is_js_loaded;
  }

  /**
   *
   * @return void
   * @author fabriceb
   * @since Aug 27, 2009
   */
  public static function setJsLoaded()
  {
    self::$is_js_loaded = true;
  }

  /**
   * Dirty way to convert fr into fr_FR
   * @param string $culture
   * @return string
   * @author fabriceb
   * @since Aug 28, 2009
   */
  public static function getLocale($culture = null)
  {
    if (is_null($culture))
    {
      $culture = sfContext::getInstance()->getUser()->getCulture();
    }

    $culture_to_locale = array(
      'fr' => 'fr_FR',
      'en' => 'en_US',
      'de' => 'de_DE',
      'it' => 'it_IT',
      'hu' => 'hu_HU'
    );

    return array_key_exists($culture, $culture_to_locale) ? $culture_to_locale[$culture] : $culture;
  }

  /**
  * @return interger $facebook_uid
  * @author fabriceb
  * @since Oct 6, 2009
  */
  public static function getAnyFacebookUid()
  {

    $cookie = self::getFacebookCookie();
    $fb_uid = $cookie['uid'];
    sfContext::getInstance()->getLogger()->info('{sfFacebookConnect} Fb_uid from cookie : '.$fb_uid);

    return $fb_uid;
  }

  /**
   *
   * @param sfWebRequest $request
   * @return string
   * @author fabriceb
   * @since Oct 12, 2009
   */
  public static function getFacebookSigParameters(sfWebRequest $request)
  {
    $parameters = $request->getParameterHolder()->getAll();

    $parameter_array = array();
    foreach ($parameters as $key => $parameter)
    {
      if (substr($key,0,3)=='fb_')
      {
        $parameter_array[] = $key.'='.$parameter;
      }
    }

    return join('&', $parameter_array);
  }

}
