=== MyPW for WordPress ===
Contributors: mypw
Tags: mypw, otp, authentication, password
Requires at least: 2.0.2
Tested up to: 3.0.1
Stable tag: trunk

This plugin allows you to implement the MyPW One-Time Password system into WordPress.

== Description ==

MyPW for WordPress allows you to implement one-time passwords (OTP) with the current WordPress authentication system.  Users can receive their OTP via keychain token, SMS, or via using soft token applications available on Blackberry, iPhone, and Android.  MyPW for WordPress can be configured to require passwords in one of three ways:

*  Login page with username, password, and your MyPW OTP value on separate fields
*  Login page with username in one field and a password consisting of a concatenation of your password and MyPW OTP value
*  Login page requiring just your username and your MyPW OTP value

MyPW tokens can be obtained at the MyPW website:

https://www.mypw.com/picktoken/

== Installation ==

1. Download the MyPW Auth WordPress package
2. Extract the ZIP into ~/wp-content/plugins
3. Log into WordPress as an administrator
4. In the Site Administration section go to "Plugins"

5. Activate the plugin "MyPW Authentication"

6. Click on Settings --> MyPW Auth

7. Select your password method. You have three options:

   1. Standard password and one-time password in different fields. With this configuration, you will be prompted for both your password and your one-time password value.
   2. Single password field with standard password and one-time password appended. With this configuration, your password becomes a combination of your password and your one-time password. For example if your password is "gate7way" and your one-time password value is "3918724", the password you would use is "gate7way3918724"
   3. One-time password only. Your password will consist of only your one-time password value.

8. To associate a token with a user, click on "Users"

9. Click "Edit" next to a user

10. Enter the user's token ID in the MyPW Token ID field

Any users who do not have a token specified will just use their standard username and password to log into WordPress.

== Frequently Asked Questions ==

= Where do I get my MyPW token? = 

Tokens can be obtained from the MyPW website: 

https://www.mypw.com/picktoken/

= I need more help! =

If you get stuck, you can always contact us at devicesupport@mypw.com.

== Screenshots ==

1. Sample login page

== Changelog ==

= 0.2 =

* Initial release

== Upgrade Notice ==

