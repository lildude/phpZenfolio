---
layout: news_item
title: "New Release: phpZenfolio 1.1"
date: "2011-03-28 14:20:38 +0000"
author: lildude
categories: release
---

You'll be pleased to know phpZenfolio is not dead. It's alive and well and now has a new release.

I've just pushed out rev 1.1 which addresses the following issues:

Use md5 to generate a uniq ID for each request instead of using intval() to ensure correct and consistent behaviour on 32-bit and 64-bit platforms. (Ticket #1)
Removed erroneous re-instantiation of processor when setting adapter.
Corrected check for safe_dir OR open_basedir so fails over to socket connection correctly (Ticket #2)
Cache only successful requests
Improved connection settings
Thanks to Jörg Ehrsam for finding and working with me to resolve the two tickets.

phpZenfolio 1.1 is now available on the download page.

If you spot any issues, let me know. Oh and don't forget to drop me a line if you have a product that uses phpZenfolio that could do with a bit of free advertising.
