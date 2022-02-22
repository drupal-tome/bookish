# Bookish CKEditor

Adds the "Code Block" and "Media Embed" CKEditor 5 plugins.

Beyond copying the JavaScript for these plugins from CKEditor, a text filter is
also provided to support Twitter and Flickr OEmbeds.

This is needed because the "Media Embed" plugin does not have support for these
providers by default, and core's OEmbed functionality is only provided in the
Media module which Bookish does not use.

Security-wise, this is a little suspect but the code is fairly contained.
