# Publications Importer

![Tests](https://github.com/localgovdrupal/localgov_publications_importer/actions/workflows/test.yml/badge.svg)

Import PDFs into your localGov Drupal site as HTML publications automatically.

Please join the #feature-publications-importer channel on LGD Slack to learn more about this.
Don't install this in your production site yet.

You can fund the development of this feature via the [LocalGov Drupal Community Fund](https://localgovdrupal.org/products/community-fund/pdf-import-discovery).


## Architecture
We work on an Import. This is a class that implements ImportInterface. It has a
number of Pages, which implement PageInterface. Extracted content is put in 
Pages in an Import, and passed to the other plugins via the importer to complete
the import process.

Operations are what happens to an Import. These can be one of three types:
  Extract: Plugin/LocalGovImporter/Extract
  Transform: Plugin/LocalGovImporter/Transform
  Save: Plugin/LocalGovImporter/Save

The process must include one extract operation and one save operation. Transform
operations are optional, and there can be any number of them.

As each operation is implemented in plugins, you can customise the import 
process to meet your requirements.

## Maintainers

This project is currently maintained by:

- Rupert Jabelman: https://www.drupal.org/u/rupertj
