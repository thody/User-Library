<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * User_Db Class
 *
 * @package		User
 * @category	User Library DB Adapter
 * @author		Adam Thody
 * @link		http://www.adamthody.com
 * @version		0.1
 */
class User_db {

	// Private variables
	var $CI;
	var $table;
	var $identity_column;
	
	/**
	 * User_Db Class Constructor
	 */		
	function User_db($params = array())
	{	
		// Instantiate CI
		$this->CI =& get_instance();
		
		// Load the Sessions class
		$this->CI->load->database();
		$this->CI->load->library('session');
		
		// Set up config vars for easy access
		$this->table = $this->CI->config->item('user_table');
		$this->identity_column = $this->CI->config->item('identity_column');
		
		log_message('debug', "User_db Class Initialized");
	}

	// --------------------------------------------------------------------
	
	/**
	 * Insert user into the users table
	 *
	 * @access	public
	 * @param	array	$user
	 * @return	bool
	 */
	function create($user = array())
	{
		// Make sure required fields are set
		// Note: username/password validation criteria are application specific and should't be established here
		if (empty($user[$this->identity_column]) OR empty($user['password']))
			return FALSE;
		
		// Return false if identity already exists (replace this with more useful error info)
		if (!$this->is_available_identity($user[$this->identity_column]))
			return FALSE;
		
		// Encrypt password
		$user['password'] = $this->_salt($user['password']);
		
		return $this->CI->db->insert($this->table, $user);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Update user in the users table
	 *
	 * @access	public
	 * @param	string	$user_id
	 * @param	array	$user
	 * @return	bool
	 */
	function update($user_id, $user = array())
	{
		// Encrypt password if it's being changed
		if (isset($user['password']))
		{
			$user['password'] = $this->_salt($user['password']);
		}
		
		$this->CI->db->where('id', $user_id);
		$update = $this->CI->db->update($this->table, $user);
		
		// If we're changing the current user's info, reset his session data
		if ($user_id == $this->get_user_id())
		{
			// unset password to prevent it from being stored in the session
			unset($user['password']);
			$this->_set_user_session($user);
		}
		
		return $update;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Delete user from the user table
	 *
	 * @access	public
	 * @param	string	$identifier		Can be user's ID or other unique identifier
	 * @return	bool
	 */
	function delete($identifier)
	{
		// Check if we're dealing with the username or the user id
		// @todo this assumes the identity column won't be numeric
		$field = (is_numeric($identifier)) ? 'id' : $this->identity_column;
		
		$this->CI->db->where($field, $identifier);
		
		return $this->CI->db->delete($this->table);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Login user
	 *
	 * @access	public
	 * @param	string	$identity
	 * @param	string	$password	Unencrypted version 
	 * @param	bool	$persistent	
	 * @return	bool
	 */
	function login($identity = FALSE, $password = FALSE, $persistent = FALSE)
	{
		// Make sure $identity and $password are set
		if ( !( $identity AND $password ) )
			return FALSE;
		
		// Look for valid user
		$user = $this->_test_user_credentials($identity, $password);

		// Handle failed login
		if ( ! $user )
			return FALSE;
		
		// Set initial user session
		$this->_set_user_session($user);
		
		// Set persistent session if requested
		if ($persistent)
		{
			$this->_set_persistent_session($user);
		}
		
		return TRUE;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Test user credentials
	 *
	 * @access	private
	 * @param	string	$identity
	 * @param	string	$password	Unencrypted version
	 * @return	bool
	 */
	function _test_user_credentials($identity, $password)
	{
		// Check username and pw		
		$this->CI->db->where($this->identity_column, $identity);
		$this->CI->db->where('password', $this->_salt($password));
		$this->CI->db->from($this->table);
		
		if ($this->CI->db->count_all_results() > 0)
		{
			// Pull additional user info
			return $this->_get_user_array($identity);
		}
		else
		{
			return FALSE;
		}
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Sets user session
	 *
	 * @access	private
	 * @param	array	$user
	 * @return	bool
	 */
	function _set_user_session($user = array())
	{		
		$this->CI->session->set_userdata('user', $user);
		return TRUE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Sets persistent user session
	 *
	 * @access	private
	 * @param	array	$user
	 * @return	bool
	 */
	function _set_persistent_session($user = array())
	{
		$this->CI->load->helper('cookie');
		
		// Generate a reasonably unique value
		$token = md5(mt_rand());
		
		// Set up session pair
		$session_pair = array(
			'identity' => $user[$this->identity_column],
			'token' => $token
			);
			
		// Set up cookie	
		$cookie = array(
			'name'   => 'persistent_session',
			'value'  => implode('|', $session_pair),
			'expire' => 60 * 60 * 24 * 91 // set to 3 months and 1 day, since persistent session table should be cleared out every 3 months
			);

		set_cookie($cookie);
		
		// Add session pair to database
		$this->CI->db->insert('persistent_sessions', $session_pair);
		
		return TRUE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Checks for a persistent user session
	 *
	 * @access	public
	 * @return	bool
	 */
	function is_persistent_session()
	{
		$this->CI->load->helper('cookie');
		
		// Retrieve cookie pair
		$cookie_pair = explode('|', get_cookie('persistent_session', TRUE));
		
		// If valid cookie exists, look for pair in the database
		if (is_array($cookie_pair))
		{
			$this->CI->db->where('identity', $cookie_pair[0]);
			$this->CI->db->where('token', $cookie_pair[1]);
			$valid_session = ($this->CI->db->count_all_results('persistent_sessions') > 0) ? TRUE : FALSE;
		}
		
		if ($valid_session)
		{
			// Get user data
			$user = $this->_get_user_array($cookie_pair[0]);
			
			// Reset persistent session
			$this->_reset_persistent_session($user, $cookie_pair[1]);
			
			// Generate normal session
			$this->_set_user_session($user);
			
			return TRUE;			
		}
		
		return FALSE;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Resets the persistent session data
	 *
	 * @access	private
	 * @param array		$user	
	 * @param string	$token
	 * @return	bool
	 */
	function _reset_persistent_session($user, $token)
	{	
		// Delete current db entry
		$this->_delete_persistent_session($user[$this->identity_column], $token);
		
		// Set new persistent session
		$this->_set_persistent_session($user);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Deletes a persistent session data
	 *
	 * @access	private
	 * @param string	$username
	 * @param string	$token
	 * @return bool
	 */
	function _delete_persistent_session($identity, $token)
	{	
		// Delete current db entry
		$this->CI->db->where($this->identity_column, $identity);
		$this->CI->db->where('token', $token);
		$this->CI->db->delete('persistent_sessions');

		return TRUE; // @todo  We might start checking results on db->delete's laterWe might start checking results on db->delete's later
	}
	
	
	
	// --------------------------------------------------------------------
	
	/**
	 * Get safe user data
	 *
	 * @access	private
	 * @param string	$identity
	 * @return	array
	 */
	function _get_user_array($identity)
	{	
		$this->CI->db->select('id AS user_id, ' . $this->identity_column);
		$this->CI->db->where($this->identity_column, $identity);
		$query = $this->CI->db->get($this->table);

		if ($query->num_rows() == 1)
			return $query->row_array();
		else
			return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Logout current user
	 *
	 * @access	public
	 * @return	bool
	 */
	function logout()
	{	
		$this->CI->load->helper('cookie');
		
		// Destroy persistent session
		$cookie_pair = explode('|', get_cookie('persistent_session', TRUE));
		$this->_delete_persistent_session($cookie_pair[0], $cookie_pair[1]);
		
		// Destroy session
		$this->CI->session->sess_destroy();

		// @todo the only reason we would want to return TRUE here is /if/ we could return FALSE under some circumstances?
		return TRUE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * See if user is logged in
	 *
	 * @access	public
	 * @return	bool
	 */
	function is_logged_in()
	{
		return (is_array($this->CI->session->userdata('user')))  ?  TRUE  :  FALSE;
	}
	
	// --------------------------------------------------------------------
	
   	/**
	 * Salt and hash a string
	 *
	 * @access private
	 * @param string	$string_to_salt
	 * @return string
	 */
	function _salt( $string_to_salt )
	{
		$this->CI->load->helper('security');
		return dohash($this->CI->config->item('encryption_key') . $string_to_salt);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Gets attr from session array
	 *
	 * @access private
	 * @param string	$attr_to_get
	 * @return string
	 */
	function _get_session_attr($attr_to_get)
	{
		$user = $this->CI->session->userdata('user');

		if (isset ($user[$attr_to_get]))
			return $user[$attr_to_get];
		else
			return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Gets user meta
	 *
	 * @access public
	 * @param string	$attr_to_get
	 * @return string
	 */	
	function get_meta($attr_to_get)
	{
		switch ($attr_to_get)
		{
			// Pull this meta from the session
			case 'user_id' :
			case 'username' :
				return $this->_get_session_attr($attr_to_get);
				break;
			
			// Pull this meta from the db
			default :
				$this->CI->db->select($attr_to_get);
				$this->CI->db->from($this->table);
				$this->CI->db->where('user_id', $this->_get_session_attr('user_id'));
				break;
		}
		
		$query = $this->CI->db->get();
		$row = $query->row();
		
		return (!empty($row->{$attr_to_get})) ? $row->{$attr_to_get} : NULL;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Sets user meta
	 *
	 * @access public
	 * @param string	$attr_to_set
	 * @param string	$value
	 * @return boolean
	 */	
	
	function set_meta($attr_to_set, $value)
	{		
		// If we're changing the username, make sure it doesn't already exist
		if ($attr_to_set == $this->identity_column AND !$this->is_available_identity($value)) return FALSE;
		
		// Update field value
		$this->CI->db->where('id', $this->_get_session_attr('user_id'));
		$this->CI->db->update($this->table, array($attr_to_set => $value));
		
		return TRUE;
	}
		
	// --------------------------------------------------------------------

	/**
	 * See if an identity is already in the database
	 *
	 * @access public
	 * @param string	$identity
	 * @return boolean
	 */
	function is_available_identity($identity)
	{
		$this->CI->db->where($this->identity_column, $identity);
		$this->CI->db->from($this->table);
		
		return ($this->CI->db->count_all_results() > 0) ? FALSE : TRUE;
	}
}
// END User Class

/* End of file User_db.php */
/* Location: ./system/application/libraries/User/adapters/User_db.php */