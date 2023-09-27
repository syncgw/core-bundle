# ![picture logo](../gui-bundle/assets/syncgw.png "sync•gw") #
 
![](https://img.shields.io/packagist/v/syncgw/core-bundle.svg)
![](https://img.shields.io/packagist/l/syncgw/core-bundle.svg)
![](https://img.shields.io/packagist/dt/syncgw/core-bundle.svg)
 
**sync•gw** is the one and only fully portable server software available providing synchronization service between nearly any mobile device and your web server.

* Written in PHP - no binary CPU depended code.
* Support of **[XML](https://en.wikipedia.org/wiki/XML)** and 
**[WBXML](http://en.wikipedia.org/wiki/WBXML)** protocol.
* Support of **[WebDAV](https://en.wikipedia.org/wiki/WebDAV)** (**CalDAV** and **CardDAV**) protocol.
* Support of **[MicroSoft Exchange ActiveSync (EAS)](http://en.wikipedia.org/wiki/Exchange_ActiveSync)** protocol (2.5, 12.0, 12.1, 14.0, 14.1, 16.0, 16.1).
* Only a web server with PHP is required to run **sync•gw** (no additional software or tools required).
* Full internationalization support.
* Multi byte support (support for e.g. Japanese language).
* Support for time zones..
* Multiple level of logging supported
* Intelligent field assignment - calculated based on mix of configuration file and probability calculation.
* Programming documentation available (see **Developers Guide** in the [Downloads](../doc-bundle/Downloads.md).
* Support for encrypted message exchange using SSL web server setting.
* Administrator browser interface with password protection.
* Contact synchronization support.
* Calendar and task synchronization support.
* Notes synchronization support.
* Experimental: Mail synchronization support.

**sync•gw** setup is very easy. Install this software bundle, check for other [bundles](../doc-bundle/Bundles.md), define a administrator password, connect a data base handler and **sync•gw** is ready for your first synchronization.

A detailed description of available configuration option is available in our browser interface documentation available in the [here](../doc-bundle/Downloads.md)).

## Installation ##
This package is the **sync•gw core bundle**. To install please go to your web server base directory and enter

```bash
composer require syncgw/core-bundle
```

## License ##
This plugin is released under the [GNU General Public License v3.0](./LICENSE).

If you enjoy my software, I would be happy to receive a donation.

<a href="https://www.paypal.com/donate/?hosted_button_id=DS6VK49NAFHEQ" target="_blank" rel="noopener">
  <img src="https://www.paypalobjects.com/en_US/DK/i/btn/btn_donateCC_LG.gif" alt="Donate with PayPal"/>
</a>

[[Documentaion](../doc-bundle/README.md)]
[[System requirements](../doc-bundle/PreReqs.md)] 
[[Available bundles](../doc-bundle/Packages.md)] 
[[List of all changes](../doc-bundle/Changes.md)] 
[[Additional Downloads](../doc-bundle/Downloads.md)] 
[[Frequently asked questions](../doc-bundle/FAQ.md)] 
[[Supported feature](../doc-bundle/Features.md)]
