Cloud Assets Module
===================
[![Build Status](https://secure.travis-ci.org/markguinn/silverstripe-cloudassets.png?branch=master)](http://travis-ci.org/markguinn/silverstripe-cloudassets)

Allows you to host all or part of the assets folder on a cloud storage container (CDN).

NOTE: This is still alpha quality software. You will probably experience some flakiness.


Requirements
------------
- Silverstripe 3.1+ (may work with 3.0, but hasn't been tested)
- Rackspace php-opencloud (not listed as a requirement with composer in case other buckets are used)

*NOTE:* You must install php-opencloud separately if you're using the Rackspace driver.

```
composer require rackspace/php-opencloud:dev-master
```


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
```

You can map multiple folders in this way or just map the whole assets folder.


How It Works
------------
1. CloudFileExtension is added to File.
2. In onAfterWrite, this extension checks if anything needs to be synced to the cloud
   or changed to a wrapped class.
3. File, Folder, and Image records are converted at that stage to the corresponding
   wrapped versions. Additional wrapper classes can be added (if you have other
   subclasses of File) via CloudAssets.wrappers. Note that any subclass that is
   not wrapped will continue to function normally and will not use the cloud.
4. Once wrapped, the file length will be checked every onAfterWrite. If the file on
   disk has been replaced it will be uploaded to the cloud storage and the local version
   truncated to the string 'CloudFile' (see CloudAssets.file_placeholder config).
5. For files (image, etc) the wrapped class overrides Link, URL, etc to point to the
   CDN version of the file.

This setup allows you to forgo mounting the cloud storage via s3fs, CloudFuse, etc but
shouldn't require changes to the Silverstripe file subsystem.


Scenarios Where This Won't Work
-------------------------------
- Modules or other contexts where files are accessed directly
- Hosting with no writable storage. The assets folder does not need to be permanent
  but it does need to be used. Session permanence should be enough, though maybe not ideal.


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