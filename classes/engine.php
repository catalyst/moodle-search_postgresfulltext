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
 * Postgres full-text search
 *
 * @package    search_postgresfulltext
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace search_postgresfulltext;

defined('MOODLE_INTERNAL') || die();


/**
 * Postgres full text search
 *
 * @package    search_postgresfulltext
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine extends \core_search\engine {

    /**
     * Marker for the start of a highlight.
     */
    const HIGHLIGHT_START = '@@HI_S@@';

    /**
     * Marker for the end of a highlight.
     */
    const HIGHLIGHT_END = '@@HI_E@@';

    /**
     * @var null|int
     */
    protected $totalresults = null;

    /**
     * Weighting for course matches
     */
    const COURSE_BOOST = 3;

    /**
     * Weighting for context matches
     */
    const CONTEXT_BOOST = 2;


    /**
     * Return true if file indexing is supported and enabled. False otherwise.
     *
     * @return bool
     */
    public function file_indexing_enabled() {
        return (bool)$this->config->fileindexing && !empty($this->config->tikaurl);
    }

    /**
     * Prepares a query, applies filters and executes it returning its results.
     *
     * @throws \core_search\engine_exception
     * @param  stdClass     $filters Containing query and filters.
     * @param  array        $accessinfo Information about contexts the user can access
     * @param  int          $limit The maximum number of results to return.
     * @return \core_search\document[] Results or false if no results
     */
    public function execute_query($filters, $accessinfo, $limit = 0) {
        global $DB, $USER;

        // Let's keep these changes internal.
        $data = clone $filters;

        $serverstatus = $this->is_server_ready();

        if ($serverstatus !== true) {
            throw new \core_search\engine_exception('engineserverstatus', 'search');
        }

        if (empty($limit)) {
            $limit = \core_search\manager::MAX_RESULTS;
        }

        // SELECT for the actual data with limits.
        $fullselect = "";
        $fullselectparams = array();

        // SELECT to get the count of records without limits.
        $countselect = "";

        $join = "";
        $fileands = array();
        $fileparams = array();

        $where = " WHERE ";
        $whereands = array();
        $whereparams = array();

        // Text highlighting SQL fragment
        // Highlight search terms using postgres's ts_headline.
        $highlightopen = self::HIGHLIGHT_START;
        $highlightclose = self::HIGHLIGHT_END;

        $title = "ts_headline(x.title, plainto_tsquery(?), 'StartSel=$highlightopen, StopSel=$highlightclose') AS title";
        $fullselectparams[] = $data->q;

        $content = "ts_headline(x.content, plainto_tsquery(?), 'StartSel=$highlightopen, StopSel=$highlightclose') AS content";
        $fullselectparams[] = $data->q;

        // Fulltext ranking SQL fragment.

        $courseboostsql = '';
        $contextboostsql = '';
        $courseboostparams = array();
        $contextboostparams = array();

        // If ordering by location, add in boost for the relevant course or context ids.
        if (!empty($filters->order) && $filters->order === 'location') {
            $coursecontext = $filters->context->get_course_context();
            $courseboostsql = ' * CASE courseid WHEN ? THEN '. self::COURSE_BOOST.' ELSE 1 END ';
            $courseboostparams = array($coursecontext->instanceid);

            if ($filters->context->contextlevel !== CONTEXT_COURSE) {
                // If it's a block or activity, also add a boost for the specific context id.
                $contextboostsql = ' * CASE contextid WHEN ? THEN '. self::CONTEXT_BOOST.' ELSE 1 END ';
                $contextboostparams = array($filters->context->id);
            }
        }

        $rank = "(
                    GREATEST (
                        ts_rank(fulltextindex, plainto_tsquery(?)),
                        MAX(
                            ts_rank(filefulltextindex, plainto_tsquery(?))
                        )
                    )
                    $courseboostsql $contextboostsql
                ) AS rank ";

        $fullselectparams[] = $data->q;
        $fullselectparams[] = $data->q;

        $fullselectparams = array_merge($fullselectparams, $courseboostparams, $contextboostparams);

        // Base search query.
        $fullselect = "SELECT *, $title, $content FROM (
                            SELECT id, docid, itemid, title, content, contextid, areaid, type,
                                courseid, owneruserid, modified, userid, description1,
                                description2, $rank, string_agg(fileid::text, ',') AS filematches ";

        $basequery = "SELECT t.*, NULL AS fileid, NULL AS filefulltextindex
                      FROM {search_postgresfulltext} t ";

        $basequerycount = "SELECT t.id, t.contextid
                           FROM {search_postgresfulltext} t ";

        $filequery = "SELECT t.*, f.fileid, f.fulltextindex AS filefulltextindex
                      FROM {search_postgresfulltext} t
                      INNER JOIN {search_postgresfulltext_file} f ON t.docid = f.docid ";

        $filequerycount = "SELECT t.id, t.contextid
                      FROM {search_postgresfulltext} t
                      INNER JOIN {search_postgresfulltext_file} f ON t.docid = f.docid ";

        $countselect = "SELECT COUNT(DISTINCT id)";

        // Get results only available for the current user.
        $whereands[] = '(owneruserid = ? OR owneruserid = ?)';
        $whereparams = array_merge($whereparams, array(\core_search\manager::NO_OWNER_ID, $USER->id));

        // Restrict it to the context where the user can access, we want this one cached.
        // If the user can access all contexts $usercontexts value is just true, we don't need to filter
        // in that case.
        $contextsql = '';
        $contextparams = [];
        if (!$accessinfo->everything && is_array($accessinfo->usercontexts)) {
            // Join all area contexts into a single array and implode.
            $allcontexts = array();
            foreach ($accessinfo->usercontexts as $areaid => $areacontexts) {
                if (!empty($data->areaids) && !in_array($areaid, $data->areaids)) {
                    // Skip unused areas.
                    continue;
                }
                foreach ($areacontexts as $contextid) {
                    // Ensure they are unique.
                    $allcontexts[$contextid] = $contextid;
                }
            }
            if (empty($allcontexts)) {
                // This means there are no valid contexts for them, so they get no results.
                return array();
            }

            list($contextsql, $contextparams) = $DB->get_in_or_equal($allcontexts);
            $contextsql = "WHERE contextid $contextsql ";
        }

        if (!$accessinfo->everything && $accessinfo->separategroupscontexts) {
            // Add another restriction to handle group ids. If there are any contexts using separate
            // groups, then results in that context will not show unless you belong to the group.
            // (Note: Access all groups is taken care of earlier, when computing these arrays.)

            // This special exceptions list allows for particularly pig-headed developers to create
            // multiple search areas within the same module, where one of them uses separate
            // groups and the other uses visible groups. It is a little inefficient, but this should
            // be rare.
            $exceptionsql = '';
            $exceptionparams = array();
            if ($accessinfo->visiblegroupscontextsareas) {
                foreach ($accessinfo->visiblegroupscontextsareas as $contextid => $areaids) {

                    list($areaidssql, $areaidparams) = $DB->get_in_or_equal($areadids);

                    $exceptionsql .= ' OR (contextid = ? AND areaid ' . $areaidsql .') ';

                    $exceptionparams = array_merge($exceptionparams, $contextid, $areaidparams);

                }
            }

            if ($accessinfo->usergroups) {
                // Either the document has no groupid, or the groupid is one that the user
                // belongs to, or the context is not one of the separate groups contexts.

                list($groupsql, $groupparams) = $DB->get_in_or_equal($accessinfo->usergroups);
                list($groupcontextsql, $groupcontextparams)
                    = $DB->get_in_or_equal($accessinfo->separategroupscontexts, SQL_PARAMS_QM, null, false);

                $whereands[] = '(groupid IS NULL OR groupid ' . $groupsql. ' OR contextid '. $groupcontextsql. ') '.$exceptionsql;
                $whereparams = array_merge($whereparams, $groupparams, $groupcontextparams, $exceptionparams);

            } else {
                // Either the document has no groupid, or the context is not a restricted one.
                list($groupcontextsql, $groupcontextparams)
                    = $DB->get_in_or_equal($accessinfo->separategroupscontexts, SQL_PARAMS_QM, null, false);

                $whereands[] = '(groupid IS NULL OR contextid '. $groupcontextsql. ') '.$exceptionsql;
                $whereparams = array_merge($whereparams, $groupcontextparams, $exceptionparams);

            }
        }

        // Course id filter.
        if (!empty($data->courseids)) {
            list($conditionsql, $conditionparams) = $DB->get_in_or_equal($data->courseids);
            $whereands[] = 'courseid ' . $conditionsql;
            $whereparams = array_merge($whereparams, $conditionparams);
        }

        // Area id filter.
        if (!empty($data->areaids)) {
            list($conditionsql, $conditionparams) = $DB->get_in_or_equal($data->areaids);
            $whereands[] = 'areaid ' . $conditionsql;
            $whereparams = array_merge($whereparams, $conditionparams);
        }

        if (!empty($data->title)) {
            $whereands[] = $DB->sql_like('t.title', '?', false, false);
            $whereparams[] = '%'.$data->title.'%';
        }


        $fileands = $whereands;
        $fileparams = $whereparams;

        if (!empty($data->timestart)) {
            $whereands[] = 't.modified >= ?';
            $whereparams[] = $data->timestart;

            $fileands[] = 'f.modified >= ?';
            $fileparams[] = $data->timestart;
        }
        if (!empty($data->timeend)) {
            $whereands[] = 't.modified <= ?';
            $whereparams[] = $data->timeend;

            $fileands[] = 'f.modified <= ?';
            $fileparams[] = $data->timeend;
        }


        // And finally the main query after applying all AND filters.
        if (!empty($data->q)) {
            $whereands[] = "t.fulltextindex @@ plainto_tsquery(?) ";
            $whereparams[] = $data->q;
            $fileands[] = " f.fulltextindex @@ plainto_tsquery(?) ";
            $fileparams[] = $data->q;
        }

        $countquery = "$countselect
                        FROM (
                            $basequerycount".
                            $where . implode(' AND ', $whereands). "
                            UNION
                            $filequerycount
                            $where ". implode(' AND ', $fileands). "
                        ) AS s
                        $contextsql
                      ";

        $params = array_merge($whereparams, $fileparams, $contextparams);

        $totalresults = $DB->count_records_sql($countquery, $params);

        $fullquery = "$fullselect
                     FROM (
                        $basequery".
                        $where . implode(' AND ', $whereands). "
                        UNION
                        $filequery
                        $where ". implode(' AND ', $fileands). "
                     ) AS s
                     $contextsql
                     GROUP BY s.id, docid, itemid, title, content, contextid, areaid, type,
                     courseid, owneruserid, modified, userid, description1,
                     description2, fulltextindex
                     ORDER BY rank DESC) AS x";

        $params = array_merge($fullselectparams, $whereparams, $fileparams, $contextparams);

        $documents = $DB->get_records_sql($fullquery, $params, 0, $limit);

        // No need for an accurate number, the number of valid results will always be lower than the
        // number of returned records.
        $this->totalresults = $totalresults;

        $numgranted = 0;

        // Iterate through the results checking its availability and whether they are available for the user or not.
        $docs = array();
        foreach ($documents as $docdata) {
            if ($docdata->owneruserid != \core_search\manager::NO_OWNER_ID && $docdata->owneruserid != $USER->id) {
                // If owneruserid is set, no other user should be able to access this record.
                continue;
            }

            if (!$searcharea = $this->get_search_area($docdata->areaid)) {
                continue;
            }

            // Switch id back to the document id.
            $docdata->id = $docdata->docid;
            unset($docdata->docid);

            $access = $searcharea->check_access($docdata->itemid);
            switch ($access) {
                case \core_search\manager::ACCESS_DELETED:
                    $this->delete_by_id($docdata->id);
                    break;
                case \core_search\manager::ACCESS_DENIED:
                    break;
                case \core_search\manager::ACCESS_GRANTED:
                    $numgranted++;

                    $doc = $this->to_document($searcharea, (array)$docdata);
                    if ($docdata->filematches) {
                        foreach (explode(',', $docdata->filematches) as $fileid) {
                            $doc->add_stored_file($fileid);
                        }
                    }
                    $docs[] = $doc;
                    break;
            }
        }

        return $docs;
    }

    /**
     * Adds a document to the search engine.
     *
     * @param \core_search\document $document
     * @param bool $fileindexing True if file indexing is to be used
     * @return bool False if the file was skipped or failed, true on success
     */
    public function add_document($document, $fileindexing = false) {
        global $DB;

        $doc = (object)$document->export_for_engine();

        $doc->docid = $doc->id;
        unset($doc->id);

        $id = $DB->get_field('search_postgresfulltext', 'id', array('docid' => $doc->docid));
        try {
            if ($id) {
                $doc->id = $id;
                $DB->update_record('search_postgresfulltext', $doc);
            } else {
                $id = $DB->insert_record('search_postgresfulltext', $doc);
            }

            $sql = "UPDATE {search_postgresfulltext} SET fulltextindex =
                        setweight(to_tsvector(coalesce(title, '')), 'A') ||
                        setweight(to_tsvector(coalesce(content, '')), 'B') ||
                        setweight(to_tsvector(coalesce(description1, '')), 'C') ||
                        setweight(to_tsvector(coalesce(description2, '')), 'C')
                    WHERE id = ? ";

            $DB->execute($sql, array($id));

        } catch (\dml_exception $ex) {
            debugging('dml error while trying to insert document with id ' . $doc->docid . ': ' . $ex->getMessage(),
                DEBUG_DEVELOPER);
            return false;
        }

        if ($fileindexing) {
            // This will take care of updating all attached files in the index.
            $this->process_document_files($document, $doc->docid);
        }

        return true;
    }



    /**
     * Deletes the specified document.
     *
     * @param string $id The document id to delete
     * @return void
     */
    public function delete_by_id($id) {
        global $DB;
        $DB->delete_records('search_postgresfulltext', array('docid' => $id));
        $DB->delete_records('search_postgresfulltext_file', array('docid' => $id));
    }

    /**
     * Delete all area's documents.
     *
     * @param string $areaid
     * @return void
     */
    public function delete($areaid = null) {
        global $DB;
        if ($areaid) {
            $DB->delete_records('search_postgresfulltext', array('areaid' => $areaid));
            $DB->execute("DELETE FROM {search_postgresfulltext_file}
                          WHERE docid NOT IN (
                            SELECT DISTINCT docid FROM {search_postgresfulltext}
                          )
                        ");
        } else {
            $DB->delete_records('search_postgresfulltext');
            $DB->delete_records('search_postgresfulltext_file');
        }
    }

    /**
     * Checks that the required table was installed.
     *
     * @return true|string Returns true if all good or an error string.
     */
    public function is_server_ready() {
        global $DB;
        if (!$DB->get_manager()->table_exists('search_postgresfulltext')) {
            return 'search_postgresfulltext table does not exist';
        }

        return true;
    }

    /**
     * It is always installed.
     *
     * @return true
     */
    public function is_installed() {
        return true;
    }

    /**
     * Returns the total results.
     *
     * Including skipped results.
     *
     * @return int
     */
    public function get_query_total_count() {
        return $this->totalresults;
    }


    /**
     * Index files attached to the document, ensuring the index matches the current document files.
     *
     * For documents that aren't known to be new, we check the index for existing files.
     * - New files we will add.
     * - Existing and unchanged files we will skip.
     * - File that are in the index but not on the document will be deleted from the index.
     * - Files that have changed will be re-indexed.
     *
     * @param document $document
     * @param integer $docid
     */
    protected function process_document_files($document, $docid) {
        if (!$this->file_indexing_enabled()) {
            return;
        }

        // Get the attached files.
        $files = $document->get_files();

        // If this isn't a new document, we need to check the exiting indexed files.
        if (!$document->get_is_new()) {

            $indexedfiles = $this->get_indexed_files($docid);

            // Go through each indexed file. We want to not index any stored and unchanged ones, delete any missing ones.
            foreach ($indexedfiles as $indexedfile) {
                $fileid = $indexedfile->fileid;

                if (isset($files[$fileid])) {
                    // Check for changes that would mean we need to re-index the file. If so, just leave in $files.
                    // Filelib does not guarantee time modified is updated, so we will check important values.
                    if ($indexedfile->modified != $files[$fileid]->get_timemodified()) {
                        continue;
                    }
                    if (strcmp($indexedfile->title, $files[$fileid]->get_filename()) !== 0) {
                        continue;
                    }
                    if ($indexedfile->filecontenthash != $files[$fileid]->get_contenthash()) {
                        continue;
                    }

                    // If the file is already indexed, we can just remove it from the files array and skip it.
                    unset($files[$fileid]);
                } else {
                    $this->delete_indexed_file($fileid);
                }
            }
        }

        // Now we can actually index all the remaining files.
        foreach ($files as $file) {
            $this->add_stored_file($document, $file);
        }
    }

    /**
     * Get the currently indexed files for a particular document, returns the total count, and a subset of files.
     *
     * @param string $docid
     * @return recordset
     */
    protected function get_indexed_files($docid) {
        global $DB;

        return $DB->get_recordset('search_postgresfulltext_file', array('docid' => $docid), 'id');
    }


    /**
     * Delete an indexed file from the index
     *
     * @param integer $fileid
     * @return boolean
     */
    protected function delete_indexed_file($fileid) {
        global $DB;

        return $DB->delete_records('search_postgresfulltext_file', array('fileid' => $fileid));
    }
    /**
     * Adds a file to the search engine.
     *
     * Conversion of files is done by unoconv except pdf which is done by pdftotext
     *
     * @param document $document
     * @param \stored_file $storedfile
     * @return void
     */
    protected function add_stored_file($document, $storedfile) {
        global $DB, $CFG;

        if ($storedfile->get_filesize() > ($this->config->maxindexfilekb * 1024) || $this->config->maxindexfilekb == 0 ) {
            echo "Skipping ".$storedfile->get_filename()." larger than {$this->config->maxindexfilekb} KB\n";
            return true;
        }

        $filedoc = $document->export_file_for_engine($storedfile);

        if (!$id = $DB->get_field('search_postgresfulltext_file', 'id', array(
                    'docid' => $filedoc['docid'],
                    'fileid' => $filedoc['fileid']
                ))) {

            $id = $DB->insert_record('search_postgresfulltext_file', $filedoc);
        }

        try {
            $sql = "UPDATE {search_postgresfulltext_file}
                    SET title = :title, fileid = :fileid, modified = :modified, filecontenthash = :filecontenthash,
                        fulltextindex = setweight(to_tsvector(:textdoc), 'B') || setweight(to_tsvector(:texttitle), 'A')
                    WHERE id = :id";

            $params = array(
                'title' => $filedoc['title'],
                'fileid' => $filedoc['fileid'],
                'modified' => $filedoc['modified'],
                'filecontenthash' => $filedoc['filecontenthash'],
                'textdoc' => $filedoc['text'],
                'texttitle' => $storedfile->get_filename(),
                'id' => $id
            );

            return $DB->execute($sql, $params);

        } catch (\Exception $e) {
            echo "Error writing index for ".$storedfile->get_filename()." (".$e->getMessage().")\n";
            return false;
        }

    }


    /**
     * Includes group support in the execute_query function.
     *
     * @return bool True
     */
    public function supports_group_filtering() {
        return true;
    }

    /**
     * Supports for sort by location within course contexts or below.
     *
     * @param \context $context Context that the user requested search from
     * @return array Array from order name => display text
     */
    public function get_supported_orders(\context $context) {
        $orders = parent::get_supported_orders($context);

        // If not within a course, no other kind of sorting supported.
        $coursecontext = $context->get_course_context(false);
        if ($coursecontext) {
            // Within a course or activity/block, support sort by location.
            $orders['location'] = get_string('order_location', 'search',
                    $context->get_context_name());
        }

        return $orders;
    }

}
