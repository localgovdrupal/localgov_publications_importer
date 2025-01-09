# Publications Importer

![Tests](https://github.com/localgovdrupal/localgov_publications_importer/actions/workflows/test.yml/badge.svg)

Import PDFs into your localGov Drupal site as HTML publications automatically.

Please join the #feature-publications-importer channel on LGD Slack to learn more about this.
Don't install this in your production site yet.

You can fund the development of this feature via the [LocalGov Drupal Community Fund](https://localgovdrupal.org/products/community-fund/pdf-import-discovery).

## How to try this out

1) Enable the module.
2) Choose "Content" -> "Import Publication" from the admin menu.
3) Upload a PDF file to the form and submit it.
4) After a few seconds, you'll get redirected to a new HTML Publication created from the supplied PDF.

If you'd like to use AI to clean up the text, you can. The default AI chat
provider will be used if one is configured. To configure one using ChatGPT,
you'll need to get an API key from OpenAI, then:

1) Choose "Configuration" -> "AI" -> "Provider Settings" -> "OpenAI Authentication" from the admin menu.
2) Click the link saying "create a new key".
3) Add your API key here. Key name and description can be whatever makes sense to you. Key type should be "Authentication". Key provider can be "Configuration" if you're just testing locally. Value is the key itself.
4) Save the key and head to "Configuration" -> "AI" -> "Provider Settings" -> "OpenAI Authentication" again.
5) This time you can choose your key from the dropdown. The key will be verified on save, so if you put in a key that's incorrect, you'll be notified here.
6) Once the key is saved, head to "Configuration" -> "AI" -> "AI Default Settings".
7) Scroll down to chat. Ensure OpenAI is selected. Choose the model you'd like to use. GPT-4o seems to work.

Now repeat the steps to upload a PDF from before. You'll notice that the form submission takes longer, and the results are cleaned up compared to what they were previously like.




Plugin structure:

We work on an Import. This has an ImportInterface.

Operations are what happens to an Import. These can be one of three types:
  Extract: Plugin/LocalGovImporter/Extract
  Transform: Plugin/LocalGovImporter/Transform
  Save: Plugin/LocalGovImporter/Save
