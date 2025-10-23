# Publications Importer

![Tests](https://github.com/localgovdrupal/localgov_publications_importer/actions/workflows/test.yml/badge.svg)

Import PDFs into your localGov Drupal site as HTML publications automatically.

Please join the #feature-publications-importer channel on LGD Slack to learn more about this.
Don't install this in your production site yet.

You can fund the development of this feature via the [LocalGov Drupal Community Fund](https://localgovdrupal.org/products/community-fund/pdf-import-discovery).

## How to try this out

1) Enable the module.
2) Choose "Content" -> "Imports" from the admin menu.
3) Click the "Import Publication" button in the top right.
3) Upload a PDF file to the form and submit it.
4) You'll be redirected back to the import screen on submission, and be able to see your uploaded file as a new import.
5) When cron next runs on the site, you'll see the import change status to "Processing". Once done, the status changes to "Completed" and a link to the resulting publication will show in the "Result" column.

## Import pipelines

The configuraton of the import process is known as an "import pipeline" and can be administered by users with the permission to do so. 
Multiple import pipelines can be created, and users can choose between them when a new import is created. 
This allows the ability to import content in different ways - for example, using different plugins or different AI prompts.

## Using AI to format the imported PDF

If you'd like to use AI to clean up or transform the text, you can. A submodule, localgov_publications_importer_ai, is included. To enable this module you will need to install the [Drupal AI module](https://www.drupal.org/project/ai) and at least one [AI provider module](https://www.drupal.org/project/ai#:~:text=complete%20AI%20applications.-,AI%20Providers,-In%20order%20to). The default AI chat provider will be used if one is configured. In the steps below will illustrate how to configure one using ChatGPT. Similar steps can be used with other AI LLM providers:

1) Enable the localgov_publications_importer_ai submodule.
2) Download and install the [Open AI provider](https://www.drupal.org/project/ai_provider_openai) module.
3) Get an [API key from OpenAI](https://platform.openai.com/api-keys) (requires an Open AI account).
4) Choose "Configuration" -> "AI" -> "Provider Settings" -> "OpenAI Authentication" from the admin menu.
5) Click the link saying "create a new key".
6) Add your API key here. Key name and description can be whatever makes sense to you. Key type should be "Authentication". Key provider can be "Configuration" if you're just testing locally. Value is the key itself.
7) Save the key and head to "Configuration" -> "AI" -> "Provider Settings" -> "OpenAI Authentication" again.
8) This time you can choose your key from the dropdown. The key will be verified on save, so if you put in a key that's incorrect, you'll be notified here.
9) Once the key is saved, head to "Configuration" -> "AI" -> "AI Default Settings".
10) Scroll down to chat. Ensure OpenAI is selected. Choose the model you'd like to use. GPT-4o seems to work.

Now repeat the steps to upload a PDF from before. You'll notice that the form submission takes longer, and the results are cleaned up compared to what they were previously like.

## Plugin structure:

This module is designed to be customisable. You can either write your own plugins to affect how content is imported, or use Drupal modules that provide plugins.

We work on an instance of ImportInterface, which is passed between plugins. There's a default implementation called Import, but you can use your own if you like.

Operations are what happens to an Import. These can be one of three types:
* Extract: Plugin/LocalGovImporter/Extract
* Transform: Plugin/LocalGovImporter/Transform
* Save: Plugin/LocalGovImporter/Save

Content is extracted from the uploaded file by an Extract plugin, and placed on an Import object. It's then transformed by any number of Transform plugins, and saved by a Save plugin.
