# Moodle Global Search - Postgres Full-Text Search Backend

This plugin allows Moodle to use Postgres full-text indexing as a backend for Moodle's global search.

The following features are provided by this plugin:

* File indexing (using Apache Tika)
* Search result ranking
* Search term highlighting
* Group support (new in Moodle 3.5)
* Ordering by course and context (new in Moodle 3.5)

## Supported Moodle Versions
This plugin currently supports Moodle:

* 3.1
* 3.4
* 3.5
* 3.6
* 3.9

## Requirements

Moodle must use Postgres as its database. MySQL and other databases types are not supported by this plugin.


## Installation
**NOTE:** Complete all of these steps before trying to enable the Global Search functionality in Moodle:

1. Get the code and copy/ install it to: `<moodledir>/search/engine/postgresfulltext`
2. Run the upgrade: `sudo -u www-data php admin/cli/upgrade.php` **Note:** the user may be different to www-data on your system.
3. Enable Global search in *Site administration > Advanced features*



## File Indexing Support
This plugin uses [Apache Tika](https://tika.apache.org/) for file indexing support. Tika parses files, extracts the text, and return it via a REST API.

### Tika Setup
Seting up a Tika test service is straight forward. In most cases on a Linux environment, you can simply download the Java JAR then run the service.

Make sure java is installed:

<pre><code>$ sudo apt-get install openjdk-8-jre-headless
</code></pre>

Then download and start Tika:
<pre><code>$ wget http://apache.mirror.amaze.com.au/tika/tika-server-1.16.jar
$ java -jar tika-server-1.16.jar
</code></pre>

This will start Tika on the host. By default the Tika service is available on: `http://localhost:9998`

### Enabling File indexing support in Moodle
Once a Tika service is available the Postgres Full-Text plugin in Moodle needs to be configured for file indexing support.<br/>
Assuming you have already followed the basic installation steps, to enable file indexing support:

1. Configure the plugin at: *Site administration > Plugins > Search > Postgres Full Text.
2. Select the *Enable file indexing* checkbox.
3. Set *Tika URL*, including the port number e.g. http://localhost:9998.
4. Click the *Save Changes* button.

### What is Tika
From the [Apache Tika](https://tika.apache.org/) website:
<blockquote>
The Apache Tika™ toolkit detects and extracts metadata and text from over a thousand different file types (such as PPT, XLS, and PDF). All of these file types can be parsed through a single interface, making Tika useful for search engine indexing, content analysis, translation, and much more. You can find the latest release on the download page. Please see the Getting Started page for more information on how to start using Tika.
</blockquote>



# Developed by Catalyst IT


This plugin was developed by Catalyst NZ:

https://www.catalyst.net.nz



# Contributing and Support

Issues, and pull requests using github are welcome and encouraged!

https://github.com/catalyst/moodle-search_postgresfulltext/issues

If you would like commercial support or would like to sponsor additional improvements
to this plugin please contact us:

https://www.catalyst.net.nz/contact-us
