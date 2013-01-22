=== deviantART muro ===
Contributors: markjaquith, mdawaffe (this should be a list of wordpress.org userid's)
Donate link: http://example.com/
Tags: images, media, comments
Requires at least: 3.0.0
Tested up to: 3.5
Stable tag: 1.0.0
License: BSD
License URI: http://opensource.org/licenses/BSD-3-Clause

Adds support for deviantART muro, the HTML5 drawing application, for image drawing in your Media Library, articles and comments.

== Description ==


== Installation ==

= Using The WordPress Dashboard =

1. Navigate to the 'Add New' Plugin Dashboard.
1. Select `deviantart-muro.zip` from your computer.
1. Upload.
1. Activate the "deviantART muro" plugin on the WordPress Plugin Dashboard.
1. If you want to enable deviantART muro comments, go to the settings panel for the "deviantART muro" plugin and check the "Allow deviantART muro comments?" checkbox then press "Save Changes".

= Using FTP =

1. Unzip the contents of deviantart-muro.zip.
1. Upload the `deviantart-muro` directory and its contents to the `/wp-content/plugins/` directory.
1. Activate the "deviantART muro" plugin through the 'Plugins' menu in WordPress.
1. If you want to enable deviantART muro comments, go to the settings panel for the "deviantART muro" plugin and check the "Allow deviantART muro comments?" checkbox then press "Save Changes".

== Frequently Asked Questions ==

= Is this plugin secure? =

Security is a complicated question, the deviantART muro plugin uses WordPress' standard upload mechanisms for adding items to your Media Library, so that part is as secure as core WordPress.

For comments upload there's an inherent risk caused by the fact that you're accepting a file upload from a potentially untrusted and unknown user. For this reason the deviantART muro comments are turned off for the default install, you must enable them from the settings panel.

The deviantART muro plugin attempts to minimise the risk by checking that the file type uploaded is a legitimate PNG image and that it is saved under a .png file extension (to avoid image polyglot attacks).

In addition to that, there are independent moderation options that you can apply to comments containing images; allowing you to set up stricter moderation rules for comments with images than those without.

Finally, if you just don't like the idea of people being able to upload files to your server, you can disable the images-in-comments part of the plugin entirely.

That covers server security, but there's also the issue of client-side security.

By using this plugin you're running Javascript from within the plugin. This Javascript is hosted on your server and is under your control - you can inspect it to satisfy yourself that it is trustworthy.

As part of the embedding process however, the client will fetch and run Javascript from deviantART's webservers. Some of this fetched Javascript needs to run in the context of a site controlled by you, which could technically allow us to access cookies and other "same origin" data within that fetched Javascript.

We have no intention of such access, but you shouldn't have to take our word for it. So if you're concerned, the `deviantart_muro_sandbox.html` file can be moved to a sandbox domain/static assets domain that has no access to your site cookies or private content. From the admin settings page for the plugin you can then configure the location of the sandbox page and the plugin will continue working normally. For further details on this please [consult the wiki](https://github.com/deviantART/embedded-deviantART-muro/wiki/How-It-Works).

= Is this plugin compatible with other media library plugins? =

Assuming the other plugins only extend the default media library behaviour rather than replace it entirely, it's highly likely that this plugin will have no issues with other media library plugins.

Plugins that replace the entire media library system are quite likely to break the media library functionality in this plugin.

= Is this plugin compatible with other comment plugins? =

Assuming the other plugins only extend the default comment behaviour rather than replace it entirely, it's highly likely that this plugin will have no issues with other comment plugins.

Plugins that replace the entire comment system are quite likely to break the comments functionality in this plugin. Some examples of this would be JetPack comments or Disqus comments.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 1.0.0 =
* Initial release.

