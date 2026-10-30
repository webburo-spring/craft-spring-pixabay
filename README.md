# Pixabay plugin for Craft CMS by Webburo spring

This plugin adds a "Search Pixabay" button to all asset selection windows. The selected pictures will be automatically downloaded from Pixabay and saved to the server.

This plugin was created by [Webburo Spring](https://www.webburo-spring.nl). Webburo Spring is not affiliated with Pixabay.

To credit the author of a picture, select Plain Text or Link fields in the plugin settings for the author name, the URL of the author's Pixabay profile and the URL of the picture's Pixabay page. The fields need to be in the field layout of the volume the pictures are uploaded to.

You need to have a Pixabay account and a Pixabay API key, which can be found at https://pixabay.com/api/docs/. Enter the API key in the plugin settings, which saves it in your `.env` file (the plugin adds the variable when it's installed, so you can also fill it in there):

```
CRAFT_SPRING_PIXABAY_KEY=your-api-key
```

If you want the original size images to be downloaded, you need to be approved for full API access. This plugin follows Pixabay's terms and conditions for full API access. 

!["Search Pixabay" button](https://github.com/webburo-spring/craft-spring-pixabay/blob/master/screenshot-button.png)
![Pixabay search results](https://github.com/webburo-spring/craft-spring-pixabay/blob/master/screenshot-search.png)
![Picture saved to the server](https://github.com/webburo-spring/craft-spring-pixabay/blob/master/screenshot-uploaded.png)
