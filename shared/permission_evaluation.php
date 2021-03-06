<?php
/**
*
* @package phpBB Extension - brunoais readOthersTopics
* @copyright (c) 2015 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace brunoais\readOthersTopics\shared;

use brunoais\readOthersTopics\shared\accesses;


/**
* Auxiliary content
*/
	
class permission_evaluation
{

	/* @var \phpbb\auth\auth */
	protected $auth;

	/* @var \phpbb\user */
	protected $user;

	/* @var \phpbb\content_visibility */
	protected $phpbb_content_visibility;

	/* @var \phpbb\db\driver\driver_interface */
	protected $db;

	/* @var \phpbb\template\template */
	protected $template;

	/* @var \brunoais\readOthersTopics\shared\permission_evaluation */
	protected $permission_evaluation;

	/* Tables */
	public $topics_table;
	public $posts_table;

	/**
	* Constructor
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\content_visibility $content_visibility, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user, $topics_table, $posts_table)
	{
		$this->auth = $auth;
		$this->phpbb_content_visibility = $content_visibility;
		$this->db = $db;
		$this->template = $template;
		$this->user = $user;
		$this->topics_table = $topics_table;
		$this->posts_table = $posts_table;
	}
	
	/**
	 * Returns whether the user has:
	 * - Full read access: accesses::FULL_READ
	 * - Can only read own topics: accesses::NO_READ_OTHER
	 * - No read access: accesses::NO_READ
	 *
	 * from the input; an associative array with all info you can give of:
	 * forum_id, topic_id, post_id, topic_type, topic_poster
	 *
	 * Any missing info is automatically checked with a database search
	 */
	public function permission_evaluate($info)
	{
		if(empty($info['forum_id']))
		{
			if(!empty($info['topic_id']))
			{
				$this->get_forum_id_and_poster_from_topic($info);
			}
			else if(!empty($info['post_id']))
			{
				$this->get_forum_id_and_poster_from_post($info);
			}
		}

		if(!$this->auth->acl_get('f_read', $info['forum_id']))
		{
			return accesses::NO_READ;
		}


		if(!$this->auth->acl_get('f_read_others_topics_brunoais', $info['forum_id']))
		{
			if($this->user->data['user_id'] == ANONYMOUS)
			{
				return accesses::NO_READ_OTHER;
			}

			if(
				isset($info['topic_type']) &&
				(
					$info['topic_type'] == POST_ANNOUNCE ||
					$info['topic_type'] == POST_GLOBAL
				)
				)
			{
				return accesses::FULL_READ;
			}

			if(!isset($info['topic_poster']))
			{
				if(!isset($info['topic_id']))
				{
					$this->get_forum_id_and_poster_from_post($info);
				}
				$this->get_poster_and_type_from_topic_id($info);
			}

			if($info['topic_poster'] != $this->user->data['user_id'])
			{
				if(!isset($info['topic_type']))
				{
					if(!isset($info['topic_id']))
					{
						$this->get_forum_id_and_poster_from_post($info);
					}
					$this->get_poster_and_type_from_topic_id($info);
				}
				if(
					$info['topic_type'] != POST_ANNOUNCE &&
					$info['topic_type'] != POST_GLOBAL
					)
				{
					return accesses::NO_READ_OTHER;
				}
			}
		}

		return accesses::FULL_READ;

	}
	
	public function access_failed()
	{
		$this->user->add_lang_ext('brunoais/readOthersTopics', 'common');
		trigger_error('SORRY_AUTH_READ_OTHER');
	}

	private function get_forum_id_and_poster_from_topic(&$info)
	{
		$sql = 'SELECT forum_id, topic_poster, topic_type
				FROM ' . $this->topics_table . '
				WHERE topic_id = ' . (int) $info['topic_id'];
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);

		$info['forum_id'] = $row['forum_id'];
		$info['topic_poster'] = $row['topic_poster'];
		$info['topic_type'] = $row['topic_type'];

		$this->db->sql_freeresult($result);
	}

	private function get_forum_id_and_poster_from_post(&$info)
	{
		$sql = 'SELECT forum_id, topic_id
				FROM ' . $this->posts_table . '
				WHERE post_id = ' . (int) $info['post_id'];
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);

		$info['forum_id'] = $row['forum_id'];
		$info['topic_id'] = $row['topic_id'];

		$this->db->sql_freeresult($result);
	}

	private function get_poster_and_type_from_topic_id(&$info)
	{
		$sql = 'SELECT topic_poster, topic_type
				FROM ' . $this->topics_table . '
				WHERE topic_id = ' . (int) $info['topic_id'];
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);

		$info['topic_poster'] = $row['topic_poster'];
		$info['topic_type'] = $row['topic_type'];

		$this->db->sql_freeresult($result);
	}

}

