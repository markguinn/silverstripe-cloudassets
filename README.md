Cloud Assets Module
===================

Allows you to host all or part of the assets folder on a cloud storage container (CDN).

I would consider this beta quality software. We're using it on a couple production sites but it has not yet been widely
tested across different configurations. You could experience some flakiness and may want to keep a backup of your assets
initially until you've put it through it's paces, especially if not using LocalCopy mode.

Versions:
[![Latest Stable Version](https://poser.pugx.org/markguinn/silverstripe-cloudassets/v/stable.png)](https://packagist.org/packages/markguinn/silverstripe-cloudassets)
[![Latest Unstable Version](https://poser.pugx.org/markguinn/silverstripe-cloudassets/v/unstable.png)](https://packagist.org/packages/markguinn/silverstripe-cloudassets)

Licence: [![License](https://poser.pugx.org/markguinn/silverstripe-cloudassets/license.png)](https://packagist.org/packages/markguinn/silverstripe-cloudassets)

Quality:
[![Build Status](https://travis-ci.org/markguinn/silverstripe-cloudassets.svg?branch=master)](http://travis-ci.org/markguinn/silverstripe-cloudassets)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/markguinn/silverstripe-cloudassets/badges/quality-score.png?s=506eb40a2197880980ff1f695bde5fe79a4f7442)](https://scrutinizer-ci.com/g/markguinn/silverstripe-cloudassets/)
[![Code Coverage](https://scrutinizer-ci.com/g/markguinn/silverstripe-cloudassets/badges/coverage.png?s=a2b5c2c4eb1029c5e064271ac764d3c60b374762)](https://scrutinizer-ci.com/g/markguinn/silverstripe-cloudassets/)

Support:
[![Gitter](https://badges.gitter.im/Join Chat.svg)](https://gitter.im/markguinn/silverstripe-cloudassets?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)


Requirements
------------
- Silverstripe 3.1+ (tested against 3.1 and master)
- Not very useful without a bucket driver such as:
	- Rackspace CloudFiles: <https://github.com/markguinn/silverstripe-cloudassets-rackspace>
	- Amazon S3: <https://github.com/edlinklater/silverstripe-cloudassets-s3>
	- (please let me know if anyone has written others)


Example
-------
Assuming you have a CloudFiles container called site-uploads:

*mysite/_config/cloudassets.yml:*
```
---
name: assetsconfig
---
CloudAssets:
  map:
    'assets/Uploads':
      Type: RackspaceBucket
      BaseURL: 'http://yourcdnbaseurl.com/'
      Container: site-uploads
      Region: ORD
      Username: yourlogin
      ApiKey: yourkey
      LocalCopy: false
```

You can map multiple folders in this way or just map the whole assets folder.


How It Works
------------
1. CloudFileExtension is added to File.
2. In onAfterWrite, this extension checks if anything needs to be synced to the cloud
   or changed to a wrapped class.
3. File and Image records are converted at that stage to the corresponding
   wrapped versions. Additional wrapper classes can be added (if you have other
   subclasses of File) via `CloudAssets.wrappers`. Note that any subclass that is
   not wrapped will continue to function normally and will not use the cloud.
4. Once wrapped, the file length will be checked every onAfterWrite. If the file on
   disk has been replaced it will be uploaded to the cloud storage and the local version
   truncated to the string 'CloudFile' (see `CloudAssets.file_placeholder` config).
   NOTE: this behaviour can be changed with the LocalCopy key in the bucket config.
   If that is true, the file will be kept in tact locally and the modification time
   will be used to keep the cloud version in sync.
5. For files (image, etc) the wrapped class overrides Link, URL, etc to point to the
   CDN version of the file.

This setup allows you to forgo mounting the cloud storage via s3fs, CloudFuse, etc but
shouldn't require changes to the Silverstripe file subsystem.


Scenarios Where This Won't Work
-------------------------------
- Hosting with no writable storage. The assets folder does not need to be permanent
  but it does need to be used. Per-request permanence should be enough, though maybe
  not ideal.


Developer(s)
------------
- Mark Guinn <mark@adaircreative.com>

Contributions welcome by pull request and/or bug report.
Please follow Silverstripe code standards (tests would be nice).

I would love for someone to implement some other drivers - S3, Swift, Google, etc.
It's very easy to implement drivers - just extend CloudBucket and implement a few
methods.


License (MIT)
-------------
Copyright (c) 2014 Mark Guinn

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the
Software, and to permit persons to whom the Software is furnished to do so, subject
to the following conditions:

The above copyright notice and this permission notice shall be included in all copies
or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE
FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.
