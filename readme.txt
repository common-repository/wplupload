=== WPlupload ===
Contributors: jayarjo
Donate link: http://plupload.com
Tags: upload, media, files
Requires at least: 2.8.9
Tested up to: 3.2
Stable tag: 1.1

Replaces default WordPress file uploader with Plupload (supports HTML5, GEARS, Silverlight, Flash, BrowserPlus).

== Description ==

WPlupload replaces default WordPress file uploader with [Plupload](http://plupload.com "Check Plupload") - flexible, configurable and highly adaptable client-side upload component. Plupload supports HTML5, GEARS, Silverlight, Flash, BrowserPlus and will fall back to regular HTML4 when no other option available. 

* HTML5 ready.
* Multi-runtime - will use whichever runtime from the list is found first.
* Supports chunking - will split the file into chunks on client-side and upload them to a server one by one.
* Doesn't depend on server-side size constraints for uploaded files.
* Can resize images on client-side, retaining EXIF, ICC and IPTC  information (at the moment available only in HTML5 and Flash runtimes).
* Supports drag & drop, where possible (not available in some browsers and runtimes).

For more information check Plupload component [website](http://plupload.com "Check Plupload").

== Installation ==

1. Go to: Plugins > Add New > upload
2. Browse for `wplupload-x.x.x.zip` file
3. Hit `Install Now`
4. Activate

or

1. Upload contents of `wplupload-x.x.x.zip` to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==


== Screenshots ==

1. 
2. 
3. 
4. 

== Changelog ==

= 1.1 =
GEARS runtime has been merged into the full js file.
Upgraded Plupload core to 1.5, more info can be found [here](http://www.plupload.com/punbb/viewtopic.php?id=1182 "Changelog for Plupload 1.5b").

= 1.0 =
Included Google GEARS js library.
BrowserPlus will load from Yahoo servers.
Upgraded Plupload core to 1.4.2, more info can be found [here](http://www.plupload.com/punbb/viewtopic.php?id=602 "Changelog for Plupload 1.4.2").

= 0.9 =
Initial release.
