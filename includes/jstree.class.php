<?php
/*{{{ALTER_TABLE_SQL*/
$SQL_ALTER_ADD = <<<SQL
ALTER TABLE `wp`.`wp_term_taxonomy` 
	ADD COLUMN `lft` INT NOT NULL DEFAULT '0' COMMENT '' AFTER `count`,
	ADD COLUMN `rgt` INT NOT NULL DEFAULT '0' COMMENT '' AFTER `lft`,
	ADD COLUMN `lvl` INT NOT NULL DEFAULT '0' COMMENT '' AFTER `rgt`,
	ADD COLUMN `pos` INT NOT NULL DEFAULT '0' COMMENT '' AFTER `lvl`,
	ADD INDEX `IDX_taxonomy_parent_pos` ( `taxonomy`, `parent`, `pos` )
;
SQL;
$SQL_ALTER_DROP = <<<SQL
ALTER TABLE `wp`.`wp_term_taxonomy` 
	DROP `lft`, DROP `rgt`, DROP `lvl`, DROP `pos` 
;
SQL;
/*}}}*/

// TO DO: better exceptions, use params
class jsTree
{
	protected $db = null;
	protected $options = null;
	protected $default = array(
		't_base'		=> 'term_taxonomy',	// the base table (containing the id, left, right, level, parent_id and position fields)
		't_data'		=> 'terms',		// table for additional fields (apart from base ones, can be the same as t_base)
		'k_d2b'			=> 'term_id',		// which key(field) from the data table maps to the base table
		'taxonomy'	=> 'category',	// default taxonomy
		'base' => array(			// which field (value) maps to what in the base (key)
			'id'				=> 'term_taxonomy_id',
			'parent_id'	=> 'parent',
			'left'			=> 'lft',
			'right'			=> 'rgt',
			'level'			=> 'lvl',
			'position'	=> 'pos'
		),
		'data' => array(
			'id'			=> 'term_id',
			'name'		=> 'name',
			'slug'		=> 'slug',
			'term_group' => 'term_group',
		)			// array of additional fields from the data table
	);

	public function __construct( $options = array() ) {/*{{{*/
		global $wpdb;
		$this->db = $wpdb;
		$this->default['t_base'] = $wpdb->prefix.$this->default['t_base'];
		$this->default['t_data'] = $wpdb->prefix.$this->default['t_data'];
		$this->options = array_merge($this->default, $options);
	}/*}}}*/

	public function get_node($id, $options = array()) {/*{{{*/
		extract($this->options);
		$fields_base = implode("`, s.`", $base);
		$fields_data = implode("`, d.`", $data);
		$sql = "
			SELECT
				s.`$fields_base`,
				d.`$fields_data`
			FROM
				`$t_base` s JOIN `$t_data` d USING (`$k_d2b`)
			WHERE
				s.`taxonomy` = '$taxonomy' AND
				s.`{$base['id']}` = d.`$k_d2b` AND
				s.`{$base['id']}` = ".(int)$id;
		$node = $this->db->get_row($sql, ARRAY_A);
		if(!$node) {
			throw new Exception('Node does not exist');
		}
		if(isset($options['with_children'])) {
			$node['children'] = $this->get_children($id, isset($options['deep_children']));
		}
		if(isset($options['with_path'])) {
			$node['path'] = $this->get_path($id);
		}
		return $node;
	}/*}}}*/

	public function get_children($id, $recursive = false) {/*{{{*/
		extract($this->options);
		$fields_base = implode("`, s.`", $base);
		$fields_data = implode("`, d.`", $data);
		$sql = false;
		if($recursive) {
			$node = $this->get_node($id);
			$sql = "
				SELECT
					s.".implode(", s.", $base).",
					d.".implode(", d.", $data)."
				FROM
					`$t_base` s JOIN `$t_data` d USING (`$k_d2b`)
				WHERE
					s.`taxonomy` = '$taxonomy' AND
					s.".$base['left']." > ".(int)$node[$base['left']]." AND
					s.".$base['right']." < ".(int)$node[$base['right']]."
				ORDER BY
					s.".$base['left']."
			";
		}
		else {
			$sql = "
				SELECT
					s.".implode(", s.", $base).",
					d.".implode(", d.", $data)."
				FROM
					`$t_base` s JOIN `$t_data` d USING (`$k_d2b`)
				WHERE
					s.`taxonomy` = '$taxonomy' AND
					s.".$base['parent_id']." = ".(int)$id."
				ORDER BY
					s.".$base['position']."
			";
		}
		return $this->db->get_results($sql, ARRAY_A);
	}/*}}}*/

	public function get_path($id) {/*{{{*/
		extract($this->options);
		$node = $this->get_node($id);
		$sql = false;
		if($node) {
			$sql = "
				SELECT
					s.".implode(", s.", $base).",
					d.".implode(", d.", $data)."
				FROM
					`$t_base` s JOIN `$t_data` d USING (`$k_d2b`)
				WHERE
					s.`taxonomy` = '$taxonomy' AND
					s.{$base['left']} < ".(int)$node[$base['left']]." AND
					s.{$base['right']} > ".(int)$node[$base['right']]."
				ORDER BY
					s.{$base['left']}
			";
		}
		return $sql ? $this->db->get_results($sql, ARRAY_A) : false;
	}/*}}}*/

	public function mk($parent_id, $position = 0, $input = array()) {/*{{{*/
		extract($this->options);
		$parent_id = (int)$parent_id;
		if($parent_id == 0) { 
			//throw new Exception('Parent_id is 0'); 
			$parent = array (
				$base['id'] => 0,
				$base['parent_id'] => 0,
				$base['left'] => 0,
				$base['right'] => 1,
				$base['level'] => 0,
				$base['position'] => 0,
			);
		} else {
			$parent = $this->get_node($parent_id, array('with_children'=> true));
		}
		if( isset($parent['children']) && $position >= count($parent['children']) ) { 
			$position = count($parent['children']); 
		} else {
			$position = 0;
		}

		$sql = array();
		$par = array();

		// PREPARE NEW PARENT
		// update positions of all next elements
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['position']." = ".$base['position']." + 1
			WHERE
				".$base['parent_id']." = ".(int)$parent[$base['id']]." AND
				".$base['position']." >= ".$position."
			";
		$par[] = false;

		// update left indexes
		$ref_lft = false;
		if( ! isset($parent['children']) ) {
			$ref_lft = $parent[$base['right']];
		}
		else if(!isset($parent['children'][$position])) {
			$ref_lft = $parent[$base['right']];
		}
		else {
			$ref_lft = $parent['children'][(int)$position][$base['left']];
		}
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['left']." = ".$base['left']." + 2
			WHERE
				".$base['left']." >= ".(int)$ref_lft."
			";
		$par[] = false;

		// update right indexes
		$ref_rgt = false;
		if( ! isset($parent['children']) ) {
			$ref_rgt = $parent[$base['right']];
		}
		else if(!isset($parent['children'][$position])) {
			$ref_rgt = $parent[$base['right']];
		}
		else {
			$ref_rgt = $parent['children'][(int)$position][$base['left']] + 1;
		}
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['right']." = ".$base['right']." + 2
			WHERE
				".$base['right']." >= ".(int)$ref_rgt."
			";
		$par[] = false;
		foreach($sql as $k => $v) {
			try {
				$this->db->query($v, $par[$k]);
			} catch(Exception $e) {
				$this->reconstruct();
				throw new Exception('Could not create');
			}
		}

		// insert new category term
		$args = array ( 
			'slug'		=> $input['slug'],
			'parent'	=> $parent_id,	// $parent[$base['id']]
			'description'	=> empty($input['description']) ? '' : $input['description'],
		);
		$ret = wp_insert_term( $input['name'], 'category', $args );
		if ( is_wp_error($ret) && $ret->get_error_message() )
			wp_die( $ret->get_error_message() );
		$term_id = $ret['term_id'];
		$term_taxonomy_id = $ret['term_taxonomy_id'];

		// update mptt values
		$args = array (
			$base['left']			=> (int)$ref_lft,
			$base['right']		=> (int)$ref_lft + 1,
			$base['level']		=> (int)$parent[$base['level']] + 1,
			$base['position']	=> $position,
		);
		// $wpdb->update( $table, $data, $where, $format = null, $where_format = null ); 
		$ret = $this->db->update( $t_base, $args, array($data['id']=>$term_id), array_fill(0,3,'%d'), '%d' );
		if ( $ret === false ) {
			echo "\n[SQL] ".$this->db->last_query;
			wp_die( "\n[ERROR] update to modify mptt values\n".print_r($args,true) );
		} else if ( $ret == 0 ) {
			wp_die( "\n[NO UPDATE] update to modify mptt values\n".print_r($args,true) );
		}

		return $term_taxonomy_id;
	}/*}}}*/

	public function mv($id, $parent, $position = 0) {/*{{{*/
		extract($this->options);
		$id			= (int)$id;
		$parent		= (int)$parent;
		if($parent == 0 || $id == 0 || $id == 1) {
			throw new Exception('Cannot move inside 0, or move root node');
		}

		$parent		= $this->get_node($parent, array('with_children'=> true, 'with_path' => true));
		$id			= $this->get_node($id, array('with_children'=> true, 'deep_children' => true, 'with_path' => true));
		if(!$parent['children']) {
			$position = 0;
		}
		if($id[$base['parent_id']] == $parent[$base['id']] && $position > $id[$base['position']]) {
			$position ++;
		}
		if($parent['children'] && $position >= count($parent['children'])) {
			$position = count($parent['children']);
		}
		if($id[$base['left']] < $parent[$base['left']] && $id[$base['right']] > $parent[$base['right']]) {
			throw new Exception('Could not move parent inside child');
		}

		$tmp = array();
		$tmp[] = (int)$id[$base['id']];
		if($id['children'] && is_array($id['children'])) {
			foreach($id['children'] as $c) {
				$tmp[] = (int)$c[$base['id']];
			}
		}
		$width = (int)$id[$base['right']] - (int)$id[$base['left']] + 1;

		$sql = array();

		// PREPARE NEW PARENT
		// update positions of all next elements
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['position']." = ".$base['position']." + 1
			WHERE
				".$base['id']." != ".(int)$id[$base['id']]." AND
				".$base['parent_id']." = ".(int)$parent[$base['id']]." AND
				".$base['position']." >= ".$position."
			";

		// update left indexes
		$ref_lft = false;
		if(!$parent['children']) {
			$ref_lft = $parent[$base['right']];
		}
		else if(!isset($parent['children'][$position])) {
			$ref_lft = $parent[$base['right']];
		}
		else {
			$ref_lft = $parent['children'][(int)$position][$base['left']];
		}
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['left']." = ".$base['left']." + ".$width."
			WHERE
				".$base['left']." >= ".(int)$ref_lft." AND
				".$base['id']." NOT IN(".implode(',',$tmp).")
			";
		// update right indexes
		$ref_rgt = false;
		if(!$parent['children']) {
			$ref_rgt = $parent[$base['right']];
		}
		else if(!isset($parent['children'][$position])) {
			$ref_rgt = $parent[$base['right']];
		}
		else {
			$ref_rgt = $parent['children'][(int)$position][$base['left']] + 1;
		}
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['right']." = ".$base['right']." + ".$width."
			WHERE
				".$base['right']." >= ".(int)$ref_rgt." AND
				".$base['id']." NOT IN(".implode(',',$tmp).")
			";

		// MOVE THE ELEMENT AND CHILDREN
		// left, right and level
		$diff = $ref_lft - (int)$id[$base['left']];

		if($diff > 0) { $diff = $diff - $width; }
		$ldiff = ((int)$parent[$base['level']] + 1) - (int)$id[$base['level']];
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['right']." = ".$base['right']." + ".$diff.",
					".$base['left']." = ".$base['left']." + ".$diff.",
					".$base['level']." = ".$base['level']." + ".$ldiff."
				WHERE ".$base['id']." IN(".implode(',',$tmp).")
		";
		// position and parent_id
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['position']." = ".$position.",
					".$base['parent_id']." = ".(int)$parent[$base['id']]."
				WHERE ".$base['id']."  = ".(int)$id[$base['id']]."
		";

		// CLEAN OLD PARENT
		// position of all next elements
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['position']." = ".$base['position']." - 1
			WHERE
				".$base['parent_id']." = ".(int)$id[$base['parent_id']]." AND
				".$base['position']." > ".(int)$id[$base['position']];
		// left indexes
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['left']." = ".$base['left']." - ".$width."
			WHERE
				".$base['left']." > ".(int)$id[$base['right']]." AND
				".$base['id']." NOT IN(".implode(',',$tmp).")
		";
		// right indexes
		$sql[] = "
			UPDATE ".$t_base."
				SET ".$base['right']." = ".$base['right']." - ".$width."
			WHERE
				".$base['right']." > ".(int)$id[$base['right']]." AND
				".$base['id']." NOT IN(".implode(',',$tmp).")
		";

		foreach($sql as $v) {
			$ret = $this->db->query($v);
			if ( $ret === false ) {
				file_put_contents('/tmp/mv1.err', print_r(array($v,$this->db->last_query),true) );
				wp_die('Could not remove');
			} else if ( $ret == 0 ) {
				file_put_contents('/tmp/mv1.err', PHP_EOL.var_export(array($v,$ret),true), FILE_APPEND );
			}
		}
		/*foreach($sql as $k => $v) {
			//echo preg_replace('@[\s\t]+@',' ',$v) ."\n";
			try {
				$this->db->query($v);
			} catch(Exception $e) {
				$this->reconstruct();
				throw new Exception('Error moving');
			}
		}*/
		return true;
	}/*}}}*/

	public function rm($id) {/*{{{*/
		extract($this->options);
		$id = (int)$id;
		if(!$id || $id === 1) { throw new Exception('Could not create inside roots'); }
		$data = $this->get_node($id, array('with_children' => true, 'deep_children' => true));

		$lft = (int)$data[$base['left']];
		$rgt = (int)$data[$base['right']];
		$pid = (int)$data[$base['parent_id']];
		$pos = (int)$data[$base['position']];
		$dif = $rgt - $lft + 1;

		$sql = array();
		// deleting node and its children from base
		$sql[] = "
      DELETE tt, t, tm 
      FROM $t_base tt 
        JOIN $t_data t ON tt.term_id = t.term_id
        LEFT JOIN wp_termmeta tm ON tt.term_id=tm.term_id
      WHERE `taxonomy` = '$taxonomy' 
        AND {$base['left']} >= $lft AND {$base['right']} <= $rgt
		";
		// shift left indexes of nodes right of the node
		$sql[] = "
      UPDATE $t_base
        SET {$base['left']} = {$base['left']} - $dif
      WHERE `taxonomy` = '$taxonomy' AND {$base['left']} > $rgt
		";
		// shift right indexes of nodes right of the node and the node's parents
		$sql[] = "
      UPDATE $t_base
        SET {$base['right']} = {$base['right']} - $dif
      WHERE `taxonomy` = '$taxonomy' AND {$base['right']} > $lft
		";
		// Update position of siblings below the deleted node
		$sql[] = "
      UPDATE $t_base
        SET {$base['position']} = {$base['position']} - 1
      WHERE `taxonomy` = '$taxonomy' AND {$base['parent_id']} = $pid AND {$base['position']} > $pos
		";
		// delete from data table
		if($data) {
			$tmp = array();
			$tmp[] = (int)$data[$k_d2b];
			if($data['children'] && is_array($data['children'])) {
				foreach($data['children'] as $v) {
					$tmp[] = (int)$v[$k_d2b];
				}
			}
			$sql[] = "
      DELETE tt, t, tm 
      FROM $t_base tt 
        JOIN $t_data t ON tt.term_id = t.term_id
        LEFT JOIN wp_termmeta tm ON tt.term_id=tm.term_id
      WHERE `taxonomy` = '$taxonomy' 
        AND tt.`$k_d2b` IN (".implode(',',$tmp).")";
		}

		foreach($sql as $v) {
			$ret = $this->db->query($v);
			if ( $ret === false ) {
				file_put_contents('/tmp/rm.err', print_r(array($v,$this->db->last_query),true) );
				wp_die('Could not remove');
			} else if ( $ret == 0 ) {
				file_put_contents('/tmp/rm.err', PHP_EOL.var_export(array($v,$ret),true), FILE_APPEND );
			}
		}
		return true;
	}/*}}}*/

	public function rn($id, $data) {/*{{{*/
		$updated = wp_update_term($id, 'category', $data);
		if ( is_wp_error($updated) && $updated->get_error_message() )
			wp_die( $updated->get_error_message() );

		/*extract($this->options);
		if(!(int)$this->db->get_var('SELECT 1 AS res FROM '.$t_base.' WHERE '.$base['id'].' = '.(int)$id)) {
			throw new Exception('Could not rename non-existing node');
		}
		if ( $updated && !is_wp_error($updated) ) {
			$tag = get_term( $updated['term_id'], 'category' );
			if ( !$tag || is_wp_error( $tag ) ) {
				if ( is_wp_error($tag) && $tag->get_error_message() )
					wp_die( $tag->get_error_message() );
				wp_die( __( 'Item not updated.' ) );
			}
		} else {
			if ( is_wp_error($updated) && $updated->get_error_message() )
				wp_die( $updated->get_error_message() );
			wp_die( __( 'Item not updated.' ) );
		}*/

		return true;
	}/*}}}*/

	public function analyze($get_errors = false) {/*{{{*/
		extract($this->options);
		$report = array();
		if((int)$this->db->get_var("SELECT COUNT(".$base['id'].") AS res FROM ".$t_base." WHERE ".$base['parent_id']." = 0") !== 1) {
			$report[] = "No or more than one root node.";
		}
		if((int)$this->db->get_var("SELECT ".$base['left']." AS res FROM ".$t_base." WHERE ".$base['parent_id']." = 0") !== 1) {
			$report[] = "Root node's left index is not 1.";
		}
		if((int)$this->db->get_var("
			SELECT
				COUNT(".$base['id'].") AS res
			FROM ".$t_base." s
			WHERE
				".$base['parent_id']." != 0 AND
				(SELECT COUNT(".$base['id'].") FROM ".$t_base." WHERE ".$base['id']." = s.".$base['parent_id'].") = 0") > 0
		) {
			$report[] = "Missing parents.";
		}
		if(
			(int)$this->db->get_var("SELECT MAX(".$base['right'].") AS res FROM ".$t_base) / 2 !=
			(int)$this->db->get_var("SELECT COUNT(".$base['id'].") AS res FROM ".$t_base)
		) {
			$report[] = "Right index does not match node count.";
		}
		if(
			(int)$this->db->get_var("SELECT COUNT(DISTINCT ".$base['right'].") AS res FROM ".$t_base) !=
			(int)$this->db->get_var("SELECT COUNT(DISTINCT ".$base['left'].") AS res FROM ".$t_base)
		) {
			$report[] = "Duplicates in nested set.";
		}
		if(
			(int)$this->db->get_var("SELECT COUNT(DISTINCT ".$base['id'].") AS res FROM ".$t_base) !=
			(int)$this->db->get_var("SELECT COUNT(DISTINCT ".$base['left'].") AS res FROM ".$t_base)
		) {
			$report[] = "Left indexes not unique.";
		}
		if(
			(int)$this->db->get_var("SELECT COUNT(DISTINCT ".$base['id'].") AS res FROM ".$t_base) !=
			(int)$this->db->get_var("SELECT COUNT(DISTINCT ".$base['right'].") AS res FROM ".$t_base)
		) {
			$report[] = "Right indexes not unique.";
		}
		if(
			(int)$this->db->get_var("
				SELECT
					s1.".$base['id']." AS res
				FROM ".$t_base." s1, ".$t_base." s2
				WHERE
					s1.".$base['id']." != s2.".$base['id']." AND
					s1.".$base['left']." = s2.".$base['right']."
				LIMIT 1")
		) {
			$report[] = "Nested set - matching left and right indexes.";
		}
		if(
			(int)$this->db->get_var("
				SELECT
					".$base['id']." AS res
				FROM ".$t_base." s
				WHERE
					".$base['position']." >= (
						SELECT
							COUNT(".$base['id'].")
						FROM ".$t_base."
						WHERE ".$base['parent_id']." = s.".$base['parent_id']."
					)
				LIMIT 1") ||
			(int)$this->db->get_var("
				SELECT
					s1.".$base['id']." AS res
				FROM ".$t_base." s1, ".$t_base." s2
				WHERE
					s1.".$base['id']." != s2.".$base['id']." AND
					s1.".$base['parent_id']." = s2.".$base['parent_id']." AND
					s1.".$base['position']." = s2.".$base['position']."
				LIMIT 1")
		) {
			$report[] = "Positions not correct.";
		}
		if((int)$this->db->get_var("
			SELECT
				COUNT(".$base['id'].") FROM ".$t_base." s
			WHERE
				(
					SELECT
						COUNT(".$base['id'].")
					FROM ".$t_base."
					WHERE
						".$base['right']." < s.".$base['right']." AND
						".$base['left']." > s.".$base['left']." AND
						".$base['level']." = s.".$base['level']." + 1
				) !=
				(
					SELECT
						COUNT(*)
					FROM ".$t_base."
					WHERE
						".$base['parent_id']." = s.".$base['id']."
				)")
		) {
			$report[] = "Adjacency and nested set do not match.";
		}
		if(
			$t_data &&
			(int)$this->db->get_var("
				SELECT
					COUNT(".$base['id'].") AS res
				FROM ".$t_base." s
				WHERE
					(SELECT COUNT(`$k_d2b`) FROM ".$t_data." WHERE `$k_d2b` = s.".$base['id'].") = 0
			")
		) {
			$report[] = "Missing records in data table.";
		}
		if(
			$t_data &&
			(int)$this->db->get_var("
				SELECT
					COUNT(`$k_d2b`) AS res
				FROM ".$t_data." s
				WHERE
					(SELECT COUNT(".$base['id'].") FROM ".$t_base." WHERE ".$base['id']." = s.`$k_d2b`) = 0
			")
		) {
			$report[] = "Dangling records in data table.";
		}
		return $get_errors ? $report : count($report) == 0;
	}/*}}}*/

	public function reconstruct($analyze = true) {/*{{{*/
		extract($this->options);
		if($analyze && $this->analyze()) { return true; }

		if(!$this->db->query("" .
			"CREATE TEMPORARY TABLE temp_tree (" .
				"".$base['id']." INTEGER NOT NULL, " .
				"".$base['parent_id']." INTEGER NOT NULL, " .
				"". $base['position']." INTEGER NOT NULL" .
			") "
		)) { return false; }
		if(!$this->db->query("" .
			"INSERT INTO temp_tree " .
				"SELECT " .
					"".$base['id'].", " .
					"".$base['parent_id'].", " .
					"".$base['position']." " .
				"FROM ".$t_base.""
		)) { return false; }

		if(!$this->db->query("" .
			"CREATE TEMPORARY TABLE temp_stack (" .
				"".$base['id']." INTEGER NOT NULL, " .
				"".$base['left']." INTEGER, " .
				"".$base['right']." INTEGER, " .
				"".$base['level']." INTEGER, " .
				"stack_top INTEGER NOT NULL, " .
				"".$base['parent_id']." INTEGER, " .
				"".$base['position']." INTEGER " .
			") "
		)) { return false; }

		$counter = 2;
		if(!$this->db->query("SELECT COUNT(*) FROM temp_tree")) {
			return false;
		}
		$this->db->nextr();
		$maxcounter = (int) $this->db->f(0) * 2;
		$currenttop = 1;
		if(!$this->db->query("" .
			"INSERT INTO temp_stack " .
				"SELECT " .
					"".$base['id'].", " .
					"1, " .
					"NULL, " .
					"0, " .
					"1, " .
					"".$base['parent_id'].", " .
					"".$base['position']." " .
				"FROM temp_tree " .
				"WHERE ".$base['parent_id']." = 0"
		)) { return false; }
		if(!$this->db->query("DELETE FROM temp_tree WHERE ".$base['parent_id']." = 0")) {
			return false;
		}

		while ($counter <= $maxcounter) {
			if(!$this->db->query("" .
				"SELECT " .
					"temp_tree.".$base['id']." AS tempmin, " .
					"temp_tree.".$base['parent_id']." AS pid, " .
					"temp_tree.".$base['position']." AS lid " .
				"FROM temp_stack, temp_tree " .
				"WHERE " .
					"temp_stack.".$base['id']." = temp_tree.".$base['parent_id']." AND " .
					"temp_stack.stack_top = ".$currenttop." " .
				"ORDER BY temp_tree.".$base['position']." ASC LIMIT 1"
			)) { return false; }

			if($this->db->nextr()) {
				$tmp = $this->db->f("tempmin");

				$q = "INSERT INTO temp_stack (stack_top, ".$base['id'].", ".$base['left'].", ".$base['right'].", ".$base['level'].", ".$base['parent_id'].", ".$base['position'].") VALUES(".($currenttop + 1).", ".$tmp.", ".$counter.", NULL, ".$currenttop.", ".$this->db->f("pid").", ".$this->db->f("lid").")";
				if(!$this->db->query($q)) {
					return false;
				}
				if(!$this->db->query("DELETE FROM temp_tree WHERE ".$base['id']." = ".$tmp)) {
					return false;
				}
				$counter++;
				$currenttop++;
			}
			else {
				if(!$this->db->query("" .
					"UPDATE temp_stack SET " .
						"".$base['right']." = ".$counter.", " .
						"stack_top = -stack_top " .
					"WHERE stack_top = ".$currenttop
				)) { return false; }
				$counter++;
				$currenttop--;
			}
		}

		$temp_fields = $base;
		unset($temp_fields['parent_id']);
		unset($temp_fields['position']);
		unset($temp_fields['left']);
		unset($temp_fields['right']);
		unset($temp_fields['level']);
		if(count($temp_fields) > 1) {
			if(!$this->db->query("" .
				"CREATE TEMPORARY TABLE temp_tree2 " .
					"SELECT ".implode(", ", $temp_fields)." FROM ".$t_base." "
			)) { return false; }
		}
		if(!$this->db->query("TRUNCATE TABLE ".$t_base."")) {
			return false;
		}
		if(!$this->db->query("" .
			"INSERT INTO ".$t_base." (" .
					"".$base['id'].", " .
					"".$base['parent_id'].", " .
					"".$base['position'].", " .
					"".$base['left'].", " .
					"".$base['right'].", " .
					"".$base['level']." " .
				") " .
				"SELECT " .
					"".$base['id'].", " .
					"".$base['parent_id'].", " .
					"".$base['position'].", " .
					"".$base['left'].", " .
					"".$base['right'].", " .
					"".$base['level']." " .
				"FROM temp_stack " .
				"ORDER BY ".$base['id'].""
		)) {
			return false;
		}
		if(count($temp_fields) > 1) {
			$sql = "" .
				"UPDATE ".$t_base." v, temp_tree2 SET v.".$base['id']." = v.".$base['id']." ";
			foreach($temp_fields as $k => $v) {
				if($k == 'id') continue;
				$sql .= ", v.".$v." = temp_tree2.".$v." ";
			}
			$sql .= " WHERE v.".$base['id']." = temp_tree2.".$base['id']." ";
			if(!$this->db->query($sql)) {
				return false;
			}
		}
		// fix positions
		$nodes = $this->db->get("SELECT ".$base['id'].", ".$base['parent_id']." FROM ".$t_base." ORDER BY ".$base['parent_id'].", ".$base['position']);
		$last_parent = false;
		$last_position = false;
		foreach($nodes as $node) {
			if((int)$node[$base['parent_id']] !== $last_parent) {
				$last_position = 0;
				$last_parent = (int)$node[$base['parent_id']];
			}
			$this->db->query("UPDATE ".$t_base." SET ".$base['position']." = ".$last_position." WHERE ".$base['id']." = ".(int)$node[$base['id']]);
			$last_position++;
		}
		if($t_data != $t_base) {
			// fix missing data records
			$this->db->query("
				INSERT INTO
					".$t_data." (".implode(',',$data).")
				SELECT ".$base['id']." ".str_repeat(", ".$base['id'], count($data) - 1)."
				FROM ".$t_base." s
				WHERE (SELECT COUNT(`$k_d2b`) FROM ".$t_data." WHERE `$k_d2b` = s.".$base['id'].") = 0 "
			);
			// remove dangling data records
			$this->db->query("
				DELETE FROM
					".$t_data."
				WHERE
					(SELECT COUNT(".$base['id'].") FROM ".$t_base." WHERE ".$base['id']." = ".$t_data.".`$k_d2b`) = 0
			");
		}
		return true;
	}/*}}}*/

	public function res($data = array()) {/*{{{*/
		extract($this->options);
		if(!$this->db->query("TRUNCATE TABLE ".$t_base)) { return false; }
		if(!$this->db->query("TRUNCATE TABLE ".$t_data)) { return false; }
		$sql = "INSERT INTO ".$t_base." (".implode(",", $base).") VALUES (%d".str_repeat(',%d', count($base) - 1).")";
		$par = array();
		foreach($base as $k => $v) {
			switch($k) {
				case 'id':
					$par[] = null;
					break;
				case 'left':
					$par[] = 1;
					break;
				case 'right':
					$par[] = 2;
					break;
				case 'level':
					$par[] = 0;
					break;
				case 'parent_id':
					$par[] = 0;
					break;
				case 'position':
					$par[] = 0;
					break;
				default:
					$par[] = null;
			}
		}
		$ps = $this->db->prepare($sql, $par);
		if(!$this->db->query($ps)) { return false; }
		$id = $this->db->insert_id();
		foreach($base as $k => $v) {
			if(!isset($data[$k])) { $data[$k] = null; }
		}
		return $this->rn($id, $data);
	}/*}}}*/

	public function dump() {/*{{{*/
		extract($this->options);
		$nodes = $this->db->get("
			SELECT
				s.".implode(", s.", $base).",
				d.".implode(", d.", $data)."
			FROM
				".$t_base." s,
				".$t_data." d
			WHERE
				s.".$base['id']." = d.`$k_d2b`
			ORDER BY ".$base['left']
		);
		echo "\n\n";
		foreach($nodes as $node) {
			echo str_repeat(" ",(int)$node[$base['level']] * 2);
			echo $node[$base['id']]." ".$node['name']." (".$node[$base['left']].",".$node[$base['right']].",".$node[$base['level']].",".$node[$base['parent_id']].",".$node[$base['position']].")" . "\n";
		}
		echo str_repeat("-",40);
		echo "\n\n";
	}/*}}}*/

	public function rebuild_position() {/*{{{*/
		extract($this->options);
/*	// set session option
		$this->db->query("SET SESSION group_concat_max_len = 1000000;");
		$sql = "
			SELECT
				{$base['parent_id']} AS parent
				, GROUP_CONCAT( {$base['id']} ORDER BY {$base['id']} ) AS list_id
			FROM `$t_base`
			WHERE `taxonomy` = '$taxonomy' 
			GROUP BY parent";
*/
		$sql = "
			SELECT
				{$base['parent_id']} AS parent, {$base['id']} AS id
			FROM `$t_base`
			WHERE `taxonomy` = '$taxonomy' 
			ORDER BY parent, id";
		$rslt = $this->db->get_results($sql, ARRAY_A);

		$i = $last_parent = 0;
		foreach( $rslt as $row ) {
			$p = $row['parent'];
			$id = $row['id'];
			if ( $last_parent==0 || $last_parent != $p ) {
				$i=0;
				$last_parent = $p;
			}
			$sql = "UPDATE `$t_base` SET `{$base['position']}` = $i WHERE `{$base['id']}` = $id";
			$this->db->update( $t_base	// table
				, array( $base['position'] => $i )	// data
				, array( $base['id'] => $id )				// where
				, array( '%d' )		// data_format
				, array( '%d' )		// where_format
			);
			++$i;
		}
	}/*}}}*/

	public function rebuild_mptt_index( $level=0, $parent=0, $index=0 ) {/*{{{*/
		extract($this->options);
		// set session option
		$sql = "
			SELECT
				{$base['id']} AS id
				, (
					SELECT COUNT(1) FROM `$t_base`
					WHERE `taxonomy` = '$taxonomy'
						AND {$base['parent_id']} = id 
				) AS cnt
			FROM `$t_base`
			WHERE `taxonomy` = '$taxonomy' 
				AND {$base['parent_id']} = $parent
			ORDER BY {$base['position']}";
		$rslt = $this->db->get_results($sql, ARRAY_A);

		foreach( $rslt as $row ) {
			$id = $row['id'];
			$cnt = $row['cnt'];
			$left = ++$index;	// start from 1

			// has children
			if ( '0' != $row['cnt'] ) {
				$index = $this->rebuild_mptt_index( $level+1, $id, $left );
			}
			$right = ++$index;

			// UPDATE row
			$this->db->update( $t_base			// table
				, array ( 	// data
					$base['level']=> $level, 
					$base['left'] => $left, 
					$base['right'] => $right 
				)
				, array( $base['id'] => $id )	// where
				, array( '%d', '%d', '%d' )		// data_format
				, array( '%d' )								// where_format
			);
		}

		return $index;
	}/*}}}*/
}
