<?php
/*
Plugin Name: URLShortener
Description: Allows to redirect any URLs to any other URLs
Version: 1.0
Author: Benjamin Schmid
Author URI: http://apps.smeanox.com
*/

if(!defined('IN_GS')){ die('you cannot load this page directly.'); }

# get correct id for plugin
$thisfile=basename(__FILE__, ".php");

# register_plugin
register_plugin(
	$thisfile,					// Plugin id
	"URLShortener",				// Plugin name
	"1.0",						// Plugin version
	"Benjamin Schmid",			// Plugin author
	"http://apps.smeanox.com",	// author website
	"Allows to redirect any URLs to any other URLs",	// Plugin description
	"pages",					// page type
	"URLShortenerAdmin"			// main function (administration)
);

add_action("pages-sidebar", "createSideMenu", array($thisfile, "URLShortener"));
add_action('index-pretemplate','checkRedirect');

$URLShortenerXML = GSDATAOTHERPATH."URLShortener.xml";

if(basename($_SERVER['PHP_SELF']) == "load.php")
{
	if($_GET['id'] == "URLShortener_css?v=1")
	{
		header("Content-Type: text/css; charset=utf-8");
		?>
		#URLShortener_long { width: 40%; }
		#URLShortener_short { width: 20%; }
		#URLShortener_addURL { width: 15%; }
        #URLShortener_prefix { width: 20%; }
        #URLShortener_changeConfig { width: 15%; }
		<?php
		die();
	}
	else if($_GET['id'] == "URLShortener")
	{
		register_style("URLShortener_css", $SITEURL.$GSADMIN."/load.php?id=URLShortener_css", 1.0, "screen");
		queue_style("URLShortener_css", GSBACK);
		
		if(isset($_GET['delete']) && file_exists($URLShortenerXML) && strlen(trim($_GET['delete'])) > 0 && preg_match("/^[A-Za-z0-9\-_]*$/", trim($_GET['delete'])) == 1)
		{
			$toDelete = trim($_GET['delete']);
			$xml = simplexml_load_file($URLShortenerXML);
			
			$nodeToDelete = $xml->xpath("//URL[@short='".$toDelete."']");
			if($nodeToDelete)
			{
				$dom = dom_import_simplexml($nodeToDelete[0]);
				$dom->parentNode->removeChild($dom);
				$xml->config->count--;
				$xml->asXML($URLShortenerXML);
				
				$success = '"'.$toDelete.'" deleted';
			}
			else
			{
				$error = '"'.$toDelete.'" not found';
			}
		}
		
		if(isset($_GET['resetXMLFile']))
		{
			rename($URLShortenerXML, $URLShortenerXML.".old");
		}
		if(isset($_GET['recoverXMLFile']))
		{
			rename($URLShortenerXML, $URLShortenerXML.".tmp");
			rename($URLShortenerXML.".old", $URLShortenerXML);
			rename($URLShortenerXML.".tmp", $URLShortenerXML.".old");
		}
		
		if(isset($_POST['URLShortener_addURL']))
		{
			if(strlen($_POST['URLShortener_long']) > 0 && strlen($_POST['URLShortener_short']) > 0)
			{
				$urlLong = trim($_POST['URLShortener_long']);
				$urlShort = trim($_POST['URLShortener_short']);
				
				if(strpos($urlLong, ':') === false) $urlLong = "http://".$urlLong;
				
				// Source: http://blog.mattheworiordan.com/post/13174566389/url-regular-expression-for-links-with-or-without-the
				if(preg_match("/^((([A-Za-z]{3,9}:(?:\/\/)?)(?:[\-;:&=\+\$,\w]+@)?[A-Za-z0-9\.\-]+|(?:www\.|[\-;:&=\+\$,\w]+@)[A-Za-z0-9\.\-]+)((?:\/[\+~%\/\.\w\-_]*)?\??(?:[\-\+=&;%@\.\w_]*)#?(?:[\.\!\/\\\w]*))?)$/", $urlLong) == 1 && preg_match("/^[A-Za-z0-9\-_]*$/", $urlShort) == 1)
				{
					checkConfigFile();
					$xml = simplexml_load_file($URLShortenerXML);
					
					if(!$xml->xpath("//URL[@short='".$urlShort."']"))
					{
						$xml->config->count++;
						$newURL = $xml->URLs->addChild("URL");
						$newURL->addAttribute("long", $urlLong);
						$newURL->addAttribute("short", $urlShort);
						$newURL->addAttribute("hits", 0);
						$xml->asXML($URLShortenerXML);
						
						$success = "URL added";
					}
					else
					{
						$error = "Short form already exists";
					}
				}
				else
				{
					$error = "Invalid characters";
				}
			}
		}
		else if(isset($_POST['URLShortener_changeConfig']))
		{
			$newPrefix = $_POST['URLShortener_prefix'];
			if(preg_match("/^[A-Za-z0-9\/\-_]*$/", $newPrefix) == 1)
			{
				checkConfigFile();
				$xml = simplexml_load_file($URLShortenerXML);
				
				$xml->config->prefix = $newPrefix;
				$xml->asXML($URLShortenerXML);
				
				$success = "Config saved";
			}
			else
			{
				$error = "Invalid characters";
			}
		}
	}
}
function URLShortenerAdmin(){
	global $URLShortenerXML, $SITEURL;
	checkConfigFile();
	$xml = simplexml_load_file($URLShortenerXML);
?>
    <h3>Config</h3>
    <p>
    <form action="load.php?id=URLShortener" method="post">
    	<label for="URLShortener_prefix" class="URLShortener_smallLabel">URL prefix (<?=$SITEURL;?><u><?=$xml->config->prefix;?></u>shortForm)</label>
    	<input type="text" class="text" id="URLShortener_prefix" name="URLShortener_prefix" value="<?=$xml->config->prefix;?>" />
        <input type="submit" class="submit" id="URLShortener_changeConfig" name="URLShortener_changeConfig" value="Save" />
    </form>
    </p>
    <h3>Add URL</h3>
    <p>
    <form action="load.php?id=URLShortener" method="post">
    	<input type="text" class="text" id="URLShortener_long" name="URLShortener_long" placeholder="URL" />
        <input type="text" class="text" id="URLShortener_short" name="URLShortener_short" placeholder="Short form" />
        <input type="submit" class="submit" id="URLShortener_addURL" name="URLShortener_addURL" value="Shorten it!" />
    </form>
    </p>
<?php
	if($xml->config->count > 0)
	{
		echo "<h3>Shortened URLs</h3>";
		echo "<table>";
		echo "<tr><th>Long</th><th>Short</th><th>Hits</th><th></th></tr>";
		
		for($i = 0; $i < $xml->config->count; $i++)
		{
			echo '<tr>'.'<td>'.'<a target="_blank" href="'.$xml->URLs->URL[$i]['long'].'">'.$xml->URLs->URL[$i]['long'].'</a>'.'</td>'.'<td>'.'<a target="_blank" href="'.$SITEURL.$xml->config->prefix.$xml->URLs->URL[$i]['short'].'">'.$xml->URLs->URL[$i]['short'].'</a>'.'</td>'.'<td>'.$xml->URLs->URL[$i]['hits'].'</td>'.'<td class="delete"><a class="delconfirm" href="load.php?id=URLShortener&amp;delete='.$xml->URLs->URL[$i]['short'].'" title="Delete '.$xml->URLs->URL[$i]['short'].'?">×</a></td>'.'</tr>';
		}
		echo "</table>";
	}
}

function checkConfigFile()
{
	global $URLShortenerXML;
	
	if(!file_exists($URLShortenerXML))
	{
		$xml = @new SimpleXMLElement("<URLShortener></URLShortener>");
		$xmlConfig = $xml->addChild("config");
		$xmlConfig->addChild("count", 0);
		$xmlConfig->addChild("prefix", "r/");
		$xml->addChild("URLs");
		$xml->asXML($URLShortenerXML);
	}
}

function checkRedirect()
{
	global $URLShortenerXML, $SITEURL;
	
	if(file_exists($URLShortenerXML))
	{
		$xml = simplexml_load_file($URLShortenerXML);
		
		$requestURL = substr(rtrim($_SERVER['REQUEST_URI'], '/'), strlen(substr($SITEURL, intval(strpos($SITEURL, "://")) + 3)) - strlen($_SERVER['HTTP_HOST']));
		
		if(substr($requestURL, 0, strlen($xml->config->prefix)) == $xml->config->prefix)
		{
			$requestURL = substr($requestURL, strlen($xml->config->prefix));
		
			$redirectTo = $xml->xpath("//URL[@short='".$requestURL."']");
			
			if($redirectTo)
			{
				$redirectTo[0]['hits'] = intval($redirectTo[0]['hits']) + 1;
				$xml->asXML($URLShortenerXML);
				
				header("Location: ".$redirectTo[0]['long'], true, 303);
				die();
			}
		}
	}
}

?>