=== Subscribe to Category ===
Contributors: dansod
Tags: subscribe to post, subscribe to category, subscribe to news, subscribe
Requires at least: 3.9
Tested up to: 4.2.3
Stable tag: 1.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Subscribe to posts within a certain category or categories.

== Description ==
This plugin lets a user subscribe and unsubscribe to posts within a certain category or categories. 
Subscribers will recieve an e-mail with a link to the actual post. E-mails to subscribers are sent once every hour with WP Cron.

The HTML form is prepared for Bootstrap framework.

Subscribers are saved as a custom post type with a possibillity to export(excel). Unsubscription needs to be confirmed by the subscriber.

The following settings and features are available for the administrator in current version:

*   Change e-mail sender from default
*   Change the title/subject for e-mails
*   Turn CSS on/off
*   Export subscribers to Excel with a possibillity to filter by categories
*   Run the cron job manually so it will fire immediately
*   Theres a note when next scheduled event for sending e-mails to subscribers is running.
*   Options for leave no trace - deletes post meta and subscribers created by this plugin.
*   Option for re-send a post on update that has already been sent.
*   Shortcode attributes for showing and hiding some categories from subscribe form. 


= What Translations are included? =
* English
* French
* Italian
* Russian
* Spanish
* Swedish
____
Have you translated this plugin to another language? Please send me your files to info@dcweb.nu and I will add them to the plugin.

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload `subscribe-to-category` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress Admin
3. Save your settings 'Settings > Subscribe'.
4. Create a page and add shortcode [stc-subscribe] to display stc form subscription.

= Shortcode Attributes =
'category_in' - Use this attribute if you only want one or several categories to be available for subscription. Value to be entered is the name of the category.
'category_not_in' - Use this attribute if you want to exclude categories to be available for subscription. Value to be entered is the name of the category.

For both attributes you can use a comma sign to separate multiple categories, like [stc-subscribe category_in="news, article"].

= Optionally but recommended =
As Wordpress Cron is depending on that you have visits on your website you should set up a cron job on your server to hit http://yourdomain.com/wp-cron.php at a regular interval to make sure that WP Cron is running as expected. In current version of Subscribe to Category the WP Cron is running once every hour, that might be an option that is changeable in future versions. 
Therefore a suggested interval for your server cron could be once every 5 minutes. 

== Screenshots ==

1. Settings page.
2. With Bootstrap framework.
3. Without Bootstrap framework, override and add your own css.
4. When resend post is enabled in settings there is a new option available when editing a post.

== Changelog ==

= 1.2.1 =
* Fixed some undefined variables that might have caused some errors for some environments
* Renamed language files for russian language to correct syntax
* Added Italian language thanks to Claudio

= 1.2.0 =
* Possibillity to re-send a post on update that has already been sent. This option needs to be activated in the settings for the plugin.
* Attribute 'category_in' added to shortcode to show only entered categories in the subscribe form. Multiple categories are separated by a comma sign.
* Attribute 'category_not_in' added to shortcode to exclude categories in the subscribe form. Multiple categories are separated by a comma sign.


= 1.1.0 =
* Added php sleep() function to prevent sending all e-mails in the same scope. 
* Using Ajax when send is manually triggered in back-end

= 1.0.0 =
* First release