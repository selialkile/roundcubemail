<?php

/*
 +-----------------------------------------------------------------------+
 | program/include/rcube_ldap_result.php                                 |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2006-2012, The Roundcube Dev Team                       |
 | Copyright (C) 2012, Kolab Systems AG                                  |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Model class that represents an LDAP search result                   |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/


/**
 * Model class representing an LDAP search result
 *
 * @package LDAP
 */
class rcube_ldap_result implements Iterator
{
    public $conn;
    public $ldap;
    public $base_dn;
    public $filter;

    private $count = null;
    private $current = null;
    private $iteratorkey = 0;

    /**
     *
     */
    function __construct($conn, $ldap, $base_dn, $filter, $count = null)
    {
        $this->conn = $conn;
        $this->ldap = $ldap;
        $this->base_dn = $base_dn;
        $this->filter = $filter;
    }

    /**
     *
     */
    public function sort($attr)
    {
        return ldap_sort($this->conn, $this->ldap, $attr);
    }

    /**
     *
     */
    public function count()
    {
        if (!isset($this->count))
            $this->count = ldap_count_entries($this->conn, $this->ldap);

        return $this->count;
    }

    /**
     *
     */
    public function entries()
    {
        return ldap_get_entries($this->conn, $this->ldap); 
    }


    /***  Implement PHP 5 Iterator interface to make foreach work  ***/

    function current()
    {
        return ldap_get_attributes($this->conn, $this->current);
    }

    function key()
    {
        return $this->iteratorkey;
    }

    function rewind()
    {
        $this->iteratorkey = 0;
        $this->current = ldap_first_entry($this->conn, $this->ldap);
    }

    function next()
    {
        $this->iteratorkey++;
        $this->current = ldap_next_entry($this->conn, $this->current);
    }

    function valid()
    {
        return (bool)$this->current;
    }

}

