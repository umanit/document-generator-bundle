# UmanIT - Document Generator Bundle

This bundle is used to drive the document generator microservice developed by UmanIT:
https://github.com/umanit/microservice-document-generator

## Installation

`$ composer require umanit/document-generator-bundle`

## Configuration

* `umanit_document_generator.base_uri`: Base URI of the API used to generate documents.
* `umanit_document_generator.encryption_key`: (Optional) Key used to crypt message before calling the API. It must
match the one defined in the micro-service.

## Usage

The only exposed service is `umanit_document_generator.document_generator`. It provides all the necessary methods to
communicate with the micro-service API.

You can generate PNG or PDF using an URL or a HTML source code string.

Examples:

```php
<?php

$generator = $container->get('umanit_document_generator.document_generator');

// Get a PNG of https://www.google.fr
$image = $generator->generatePngFromUrl('https://www.google.fr');

// Get a PDF from a HTML code source
$pdf = $generator->generatePdfFromHtml('<html><body>Hello World!</body></html>');
```

Each methods provides an additional parameter to specify [other supported options by the micro-service](https://github.com/umanit/microservice-document-generator#parameters),
such as `decode` for HTML generation or `pageOptions` and `scenario` for both types.

## Data encryption

By default, messages are not encrypted before calling the API. If you want to enable this feature, ensure you have
define the `umanit_document_generator.encryption_key` configuration the same as in the micro-service then call the
`encryptData(true)` method on the service. The encryption could be disabled the same way by calling
`encryptData(false)`.
