### `login.php` lazy session login file:

~~~BASH
cd /var/www/html/mediawiki-1.23.13
wget https://raw.githubusercontent.com/malavolti/mediawiki-shibboleth-authentication/master/login.php -O login.php
~~~


### `LocalSettings.php` configuration example to add

~~~PHP
require_once('extensions/ShibAuthPlugin.php');

## Allow for empty paswords
$wgMinimalPasswordLength = 0;

## Last portion of the shibboleth WAYF url for lazy sessions.
## This value is found in your shibboleth.xml file on the setup for your SP
## WAYF url will look something like: /Shibboleth.sso/WAYF/$shib_WAYF
## $shib_WAYF = "Login";

## Are you using an old style WAYF (Shib 1.3) or new style Discover Service (Shib 2.x)?
## Values are WAYF or DS, defaults to WAYF
##$shib_WAYFStyle = "DS";
$shib_WAYFStyle = "CustomLogin";

## Default for compatibility with previous version: false
$shib_Https = true;

## Prompt for user to login
$shib_LoginHint = "Login with SSO";

## Prompt for user to log out
$shib_LogoutHint = "Logout";

## Where is the assertion consumer service located on the website?
## Default: "/Shibboleth.sso"
##$shib_AssertionConsumerServiceURL = "";
$shib_AssertionConsumerServiceURL = $wgScriptPath . "/login.php";

## Map Real Name to what Shibboleth variable(s)?
##$shib_RN = isset($_SERVER['HTTP_COMMON_NAME']) ? $_SERVER['HTTP_COMMON_NAME'] : null;
if (array_key_exists("cn", $_SERVER)) {
   $shib_RN = $_SERVER['cn'];
} else if (array_key_exists("givenName", $_SERVER) && array_key_exists("sn", $_SERVER)) {
   $shib_RN = ucfirst(strtolower($_SERVER['givenName'])) . ' '
            . ucfirst(strtolower($_SERVER['sn']));
}

## Map e-mail to what Shibboleth variable?
##$shib_email = isset($_SERVER['HTTP_EMAIL']) ? $_SERVER['HTTP_EMAIL'] : null;
$shib_email = isset($_SERVER['mail']) ?  $_SERVER['mail'] : null;

## Field containing groups for the user and field containing the prefix to be searched (and stripped) from wiki groups
$shib_groups = isset($_SERVER['isMemberOf']) ? $_SERVER['isMemberOf'] : null;

//This value must match with the FolderID of Wiki on the Grouper instance
$shib_group_prefix = "wiki.fqdn.example.it";

## Should pre-existing groups be deleted?
## If groups are fetched only from Shibboleth it should be true
## if memberships are granted from mediawiki User rights management
## page, it should be false
## PLEASE NOTE: with $shib_group_delete = false, in order to revoke
## a membership it should be deleted both from Shibboleth and 
## User rights management page!
$shib_group_delete = false;

## The ShibUpdateUser hook is executed on login.
## It has two arguments:
## - $existing: True if this is an existing user, false if it is a new user being added
## - &$user: A reference to the user object. 
##           $user->updateUser() is called after the function finishes.
## In the event handler you can change the user object, for instance set the email address or the real name
## The example function shown here should match behavior from previous versions of the extension:

$wgHooks['ShibUpdateUser'][] = 'ShibUpdateTheUser';

function ShibUpdateTheUser($existing, &$user) {
        global $shib_email;
        global $shib_RN;
        if (! $existing) {
                if($shib_email != null)
                        $user->setEmail($shib_email);
                if($shib_RN != null)
                        $user->setRealName($shib_RN);
        }
        return true;
}

## This is required to map to something
## You should beware of possible namespace collisions, it is best to chose
## something that will not violate MW's usual restrictions on characters
## Map Username to what Shibboleth variable?
##$shib_UN = isset($_SERVER['HTTP_UID']) ? $_SERVER['HTTP_UID'] : null;
$shib_UN = isset($_SERVER['eppn']) ? ucfirst(strtolower($_SERVER['eppn'])) : null;

## hide "IP login" and default login link
$wgShowIPinHeader = false;
function NoLoginLinkOnMainPage( &$personal_urls ){
    unset( $personal_urls['login'] );
    unset( $personal_urls['anonlogin'] );
    return true;
}
$wgHooks['PersonalUrls'][]='NoLoginLinkOnMainPage';

## to disable factory user login
function disableUserLoginSpecialPage(&$list) {
        unset($list['Userlogin']);
        return true;
}
$wgHooks['SpecialPage_initList'][]='disableUserLoginSpecialPage';

## Add to permit the management of the User rights
$wgUserrightsInterwikiDelimiter = '#';

## Activate Shibboleth Plugin
SetupShibAuth();
~~~

### `mediawiki.conf` Apache2 (>=2.4) site configuration example

~~~APACHE
<IfModule mod_alias.c>
  Alias /wiki /var/www/html/mediawiki-1.23.13/

  <Directory /var/www/html/mediawiki-1.23.13/>
    Options Indexes MultiViews FollowSymLinks
    Order deny,allow
    Allow from all
  </Directory>

  <Location /wiki>
    AuthType shibboleth
    require shibboleth
  </Location>

  <Location /wiki/login.php>
    AuthType shibboleth
    ShibRequestSetting requireSession true
    require shib-attr entitlement urn:mace:example.it:wiki
  </Location>

</IfModule>
~~~
