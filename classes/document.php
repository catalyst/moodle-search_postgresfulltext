<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Document representation.
 *
 * @package    search_postgresfulltext
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace search_postgresfulltext;

defined('MOODLE_INTERNAL') || die();


require_once($CFG->libdir.'/filelib.php');

/**
 * Represents a document to index.
 *
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document extends \core_search\document {

    /**
     * @var config stdClass
     */
    private $config = null;

    /**
     * Constructor
     *
     * @param integer $itemid
     * @param string $componentname
     * @param integer $areaname
     */
    public function __construct($itemid, $componentname, $areaname) {
        parent::__construct($itemid, $componentname, $areaname);
        $this->config = get_config('search_postgresfulltext');
    }

    /**
     * Overwritten to use markdown format as we use markdown for highlighting.
     *
     * @return int
     */
    protected function get_text_format() {
        return FORMAT_HTML;
    }

    /**
     * Formats a text string coming from the search engine.
     *
     * @param  string $text Text to format
     * @return string HTML text to be renderer
     */
    protected function format_text($text) {
        // Since we allow output for highlighting, we need to encode html entities.
        // This ensures plaintext html chars don't become valid html.
        $out = s($text);

        $startcount = 0;
        $endcount = 0;

        // Remove end/start pairs that span a few common seperation characters. Allows us to highlight phrases instead of words.
        $regex = '|'.engine::HIGHLIGHT_END.'([ .,-]{0,3})'.engine::HIGHLIGHT_START.'|';
        $out = preg_replace($regex, '$1', $out);

        // Now replace our start and end highlight markers.
        $out = str_replace(engine::HIGHLIGHT_START, '<span class="highlight">', $out, $startcount);
        $out = str_replace(engine::HIGHLIGHT_END, '</span>', $out, $endcount);

        // This makes sure any highlight tags are balanced, incase truncation or the highlight text contained our markers.
        while ($startcount > $endcount) {
            $out .= '</span>';
            $endcount++;
        }
        while ($startcount < $endcount) {
            $out = '<span class="highlight">' . $out;
            $endcount++;
        }

        return parent::format_text($out);
    }


    /**
     * Export the data for the given file in relation to this document.
     *
     * @param \stored_file $file The stored file we are talking about.
     * @return array
     */
    public function export_file_for_engine($file) {
        $data = array();
        // Going to append the fileid to give it a unique id.
        $data['docid'] = $this->data['id'];
        $data['fileid'] = $file->get_id();
        $data['filecontenthash'] = $file->get_contenthash();
        $data['title'] = $file->get_filename();
        $data['modified'] = $file->get_timemodified();
        $data['text'] = $this->extract_text_from_file($file);

        return $data;
    }

    /**
     * Extract text from a file using Apache Tika
     *
     * @param \storedfile $storedfile
     * @return string|bool
     */
    private function extract_text_from_file($storedfile) {
        if (empty($this->config->tikaurl) || !$this->config->fileindexing) {
            return false;
        }

        $curl = new \Curl();
        $url = $this->config->tikaurl."/tika/form";
        $text = $curl->post($url, array("upload" => $storedfile));

        if ($curl->info['http_code'] != 200) {
            return false;
        }

        return $text;
    }
}
