# FIM #

![Status: Alpha](http://img.shields.io/badge/status-alpha-yellow.svg "Alpha")
![License: CC BY-NC-SA 4.0](http://img.shields.io/badge/license-CC%20BY--NC--SA%204.0-red.svg "CC BY-NC-SA 4.0")

Framework Improved is a fast and lightweight PHP framework. It makes use of
brand new PHP technologies and thus requires PHP 5.4, recommending PHP 5.5.

## Main features ##

- Simplification of web developing by providing a MVC layer
- Comfortable and integrated handling of database access
- Access control via advanced rules system
- Integration of PHP's intl library for localization
- Provides several mechanism to come to grips with PHP's inabilities when
  developing on Windows or with 32 bit PHP versions.

## FAQ ##

### Why another PHP framework? ###
First of all, developing one's own framework is a process that teaches yourself
a lot. When facing a "normal" web application, you don't want to deal with all
this request parsing, internationalization problems and even incompatibilities
between the different platforms your PHP is working on. And you think you don't
have to deal with those stuff because what you do seems to work. But in fact,
it does not work in edge cases (which is quite everything you can't imagine).
So you could make use of one of the existing frameworks.
I began reading the Symfony book and Zend Framework guide and asked myself why
everything is so difficult. Those frameworks are great and provide lots of
tools, but it is difficult to understand why the developer has to write such a
lot of code just to make easy things happen.
Of course this is due to their complexity and their great coverage of quite
every aspect you can think of. But you often do not use all these features.
Moreover, these frameworks allow you lots of choices - which is great, but fewer
choices would perhaps allow better optimization and a simpler design.
So FIM is aimed to be fast, lightweight - but maybe a bit restrictive. FIM is
designed for use with Smarty template engine. You may disagree with this; but
then you have to rewrite portions of FIM's code that interact with Smarty.
FIM is designed to have an own small database ORM layer which cannot do as much
as e.g. Doctrine. However, it is a quite nice piece of code that allows to
simplify your life when dealing with common table structures. But if you want to
use Doctrine, you once again have to rewrite parts of FIM's internals.

### What is meant with "PHP's inabilities"? ###
This is one of the edge cases I have spoken of. You may rely on file system
functions such as is_file or filesize. If you don't read the manual accurately
(why should you for such a simple function), you won't notice that these
functions don't work for files that are larger than 2 GB (new x64 PHPs _may_ be
an exception, but in fact, the bug ticket is still open). As you don't have such
large files, this won't touch you. But one day... FIM provides a class that
uses the best sources to get real file states.
Another edge case only occurs on Windows: Having special characters in filenames
can be very... interesting. While NTFS is Unicode-aware, PHP for Windows isn't.
You will never be able detect a file with the name ∞.txt. Even better: if you
iterate through a directory with a file named like this by using
DirectoryIterator or any other unsuspicious means, PHP will throw an exception
as soon as you reach this inaccessible file. FIM provides a replacement for
DirectoryIterator and methods that allow to convert filenames to their CodePage
counterparts (a method that allows to access at least some Unicode filename).

### Where does the name came from? ###
FIM is the improved rewrite of a framework I wrote before. While this first
framework was solely intended to be used by applications of mine (and it is in
productive use), I wanted to build a framework that based on a solid concept and
was written in one piece. Big parts of the older framework were reused in FIM,
but lots of things are new.

### Why the restrictive license? ###
Designing FIM was a lot of work. But a framework alone does not do anything
without the application built on top of it. So if you are not intending to make
profit with your application, you may use, modify or redistribute FIM (see the
license for more information). If you however intend to make money out of your
product built on FIM (which is more than 'please donate'), we will find an
agreement.

### Why the restrictive PHP version? ###
FIM does not support PHP 5.3. Please keep in mind that PHP 5.3 was released in
2009 and support stopped in August 2014. If your webhoster does not support
newer versions, you should consider lighting a fire under them...
There are lots of nice functions added in newer PHP versions and it is time to
use these features.
Even supporting PHP 5.4 adds some "legacy" code to FIM. If PHP 5.5 was required,
some improvements in programming logic could be made (try...finally). If PHP 5.6
was required, some improvements in performance could be made (variadic
functions). And some features of the I18N class won't work with PHP 5.4 (this
is always marked in the function documentation) as they require a newer version
of the intl extension. As this newer version also supports named arguments in
messages, PHP 5.5 is highly recommended.

### For a start... ###
You may have a look at the FIM minimal application. This application does the
same as Zend Framework's Skeleton Application. If you compare the efforts, you
should see what I meant with "superfluous code" in other frameworks.