# resqu-server
Php queue implementation

## Dependencies
* [PHP 5.6+](http://php.net/manual/en/install.php) (runtime)
  * [ext-pcntl](http://php.net/manual/en/book.pcntl.php)
  * [ext-posix](http://php.net/manual/en/book.posix.php)
  * [ext-proctitle](http://php.net/manual/en/book.proctitle.php) (optional)
* [Composer - global](https://getcomposer.org/doc/00-intro.md) (dev / build)
* [Java 7+](https://java.com/en/download/help/download_options.xml) (dev / build)

## Development environment setup
After installing dependencies and cloning the project, navigate into **root directory** and run:

**LINUX**
```
./gradlew setupDev
```
**WINDOWS**
```
gradlew setupDev
```

## Distribution
To generate distribution archive, navigate into **root directory** and run:

**LINUX**
```
./gradlew dist
```
**WINDOWS**
```
gradlew dist
```

## Build system
All basic tasks are handled by Gradle build system. Build tasks can be executed from **project root** like this:

**LINUX**
```
./gradlew <task1> <task2>...
```
**WINDOWS**
```
gradlew <task1> <task2>...
```

List of all available tasks:

**LINUX**
```
./gradlew tasks
```
**WINDOWS**
```
gradlew tasks
```