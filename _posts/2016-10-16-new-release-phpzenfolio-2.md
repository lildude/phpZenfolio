---
layout: news_item
title: "New Release: phpZenfolio 2.0.0"
date: "2016-10-16 14:44:02 +0100"
author: lildude
categories: release
---

Wow!!! Look at that, a new release of phpZenfolio!!! :grin:  phpZenfolio 2.0.0 is finally here.

So what's new in phpZenfolio 2.0.0?  I'm glad you asked.

For a start, it's a complete rewrite.  Rather than maintaining my own code to perform the HTTP requests, I've switched to using [Guzzle](http://guzzle.readthedocs.org/en/latest/index.html).  This allows me to concentrate on phpZenfolio and let someone else concentrate on the finer details of actually talking HTTP.  It does however mean phpZenfolio 2.0.0 and later is _not_ a drop in replacement for phpZenfolio 1.x.

This has a big advantage in now phpZenfolio can take advantage of Guzzle's functionality without too much effort.  Whilst phpZenfolio still doesn't have support for asynchronous requests, it shouldn't be too hard to implement it in future as Guzzle already has this functionality - PRs welcome :wink:.  This use of Guzzle also means we can extend phpZenfolio with relative ease.

phpZenfolio is now installed using the industry standard method of using [Composer](https://getcomposer.org/).  This makes it easier to integrate phpZenfolio with your projects and pull in all of the dependencies.

Some other changes include the publication of the test suite.  I used to have a test suite before, but due to the embedding of credentials, it was kept private.  The switch to Guzzle means I can use Mock objects to test phpZenfolio without revealing any credentials.

A few lesser changes and improvements:

- phpZenfolio now uses semantic versioning.
- phpZenfolio is now licensed under the [MIT license](https://opensource.org/licenses/MIT).
- Unit tests are run with every push to the GitHub repository.
- PSR-1, PSR-2, and PSR-4 coding standards are implemented and enforced by unit testing.

Other than that, phpZenfolio is pretty much the same as it was before.

So what's on the cards for phpZenfolio in future?

I'd also like to introduce asynchronous uploads for a start, and then maybe extend that to all requests. If you'd like to help out, please feel free to do so.  Check the [About](/about) page or the [CONTRIBUTING.md](https://github.com/lildude/phpZenfolio/blob/master/CONTRIBUTING.md) for more details on how you can help.

If you have any questions or hit any problems, please open an [issue](https://github.com/lildude/phpZenfolio/issues) and I'll do my best to help you out as soon as I can.

So what are you waiting for?  Go [download](/downloads) phpZenfolio 2.0.0 **NOW!!**
