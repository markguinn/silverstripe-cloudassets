Cloud Assets Module
===================

In this first version it simply allows you to map one or more paths
within your assets folder to a parallel location on a CDN. It is
doesn't handle the CMS side of things at all, instead assuming you're
somehow mounting the folders within /assets/ using S3QL, CloudFuse,
CloudFront, s3fs, etc.

Hopefully we can explore the CMS side in the future and remove the
requirement for manually mounting the cloud storage to assets.

NOTE: This is not usable yet. It's very close but it's still just a prototype.


Requirements
------------
- Silverstripe 3.1+ (may work with 3.0, but hasn't been tested)


Example
-------
Install something like S3QL to mount your cloud storage container to
a folder. Assuming you've mounted the storage to /assets/Uploads:

*mysite/_config/cloudassets.yml:*
```
---
name: assetsconfig
---
CloudAssets:
  map:
    'assets/Uploads': 'http://yourcdnbaseurl.com/'
```


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
6. For folders, the wrapped class doesn't do much, except that if you try to change the
   name it throws an error. In the future we could cause it to actually rename all the
   objects underneath it.

This setup allows you to forgo mounting the cloud storage via s3fs, CloudFuse, etc but
shouldn't require changes to the Silverstripe file subsystem.


TODO
----
- Image resizing
- Implement Rackspace CloudFiles driver
- I would love for someone to implement some other drivers


Developer(s)
------------
- Mark Guinn <mark@adaircreative.com>

Contributions welcome by pull request and/or bug report.
Please follow Silverstripe code standards.


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