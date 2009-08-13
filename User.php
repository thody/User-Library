<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * User Class
 *
 * @package		User
 * @category	User Library
 * @author		Adam Thody
 * @link		http://www.adamthody.com
 * @version		0.1
 */
class User {

	// Private variables
	var $CI;
	
	/**
	 * User Class Constructor
	 */		
	function User($params = array())
	{	
		// Instantiate CI
		$this->CI =& get_instance();
		
		// Load the Sessions class
		$this->CI->load->database();
		$this->CI->load->library('session');
			
		log_message('debug', "User Class Initialized");
	}

	// --------------------------------------------------------------------
	
	/**
	 * Insert user into the users table
	 *
	 * @access	public
	 * @param	array
	 * @return	bool
	 */
	function create($user = array())
	{
		
		// Encrypt password
		$user['password'] = $this->_salt($user['password']);
		
		return $this->CI->db->insert('users', $user);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Update user in the users table
	 *
	 * @access	public
	 * @param	string
	 * @param	array
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
		$update = $this->CI->db->update('users', $user);
		
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
	 * Delete user from the users table
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function delete($identifier)
	{
		// Check if we're dealing with the username or the user id
		if (is_numeric($identifier))
		{
			$field = 'id';
		}
		else
		{
			$field = 'username';
		}
		
		$this->CI->db->where($field, $identifier);
		
		return $this->CI->db->delete('users');
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Login user
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	function login($username = NULL, $password = NULL, $persistent = FALSE)
	{		
		// Look for valid user
		$user = $this->_test_user_credentials($username, $password);

		// Handle failed login
		if ( ! $user )
		{
			return FALSE;
		}
		
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
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	function _test_user_credentials($username, $password)
	{
		// Check username and pw		
		$this->CI->db->where('username', $username);
		$this->CI->db->where('password', $this->_salt($password));
		$this->CI->db->from('users');
		
		if ($this->CI->db->count_all_results() > 0)
		{
			// Pull additional user info
			return $this->_get_user_array($username);
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
	 * @param	array
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
	 * @param	array
	 * @return	bool
	 */
	function _set_persistent_session($user = array())
	{
		$this->CI->load->helper('cookie');
		
		// Generate a reasonably unique value
		$token = md5(mt_rand());
		
		// Set up session pair
		$session_pair = array(
			'username' => $user['username'],
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
	 * @param	array
	 * @return	bool
	 */
	function check_persistent_session()
	{
		$this->CI->load->helper('cookie');
		
		// Retrieve cookie pair
		$cookie_pair = explode('|', get_cookie('persistent_session', TRUE));
		
		// If valid cookie exists, look for pair in the database
		if (is_array($cookie_pair))
		{
			$this->CI->db->where('username', $cookie_pair[0]);
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
	 * @param array
	 * @param string
	 * @return	bool
	 */
	function _reset_persistent_session($user, $token)
	{	
		// Delete current db entry
		$this->_delete_persistent_session($user['username'], $token);
		
		// Set new persistent session
		$this->_set_persistent_session($user);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Deletes a persistent session data
	 *
	 * @access	private
	 * @param string
	 * @param string
	 */
	function _delete_persistent_session($username, $token)
	{	
		// Delete current db entry
		$this->CI->db->where('username', $username);
		$this->CI->db->where('token', $token);
		$this->CI->db->delete('persistent_sessions');
	}
	
	
	
	// --------------------------------------------------------------------
	
	/**
	 * Get safe user data
	 *
	 * @access	private
	 * @param string
	 * @return	bool
	 */
	function _get_user_array($username)
	{	
		$this->CI->db->select('id AS user_id, username, email');
		$this->CI->db->where('username', $username);
		$query = $this->CI->db->get('users');
		return $query->row_array();
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
		return TRUE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Check if user is logged in
	 *
	 * @access	public
	 * @return	bool
	 */
	function logged_in()
	{
		if (is_array($this->CI->session->userdata('user')))
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	// --------------------------------------------------------------------
	
   /**
	* Salt and hash a string
	*
	* @access private
	* @param string
	* @return string
	*/
	function _salt( $string )
	{
		$this->CI->load->helper('security');
		return dohash($this->CI->config->item('encryption_key') . $string);
	}
	
	// --------------------------------------------------------------------
	
	/**
		* Gets username from session
		*
		* @access public
		* @return string
		*/
		function get_username()
		{
			$user = $this->CI->session->userdata('user');
			return $user['username'];
		}
	
	// --------------------------------------------------------------------
	
	/**
		* Gets user_id from session
		*
		* @access public
		* @return string
		*/
		function get_user_id()
		{
			$user = $this->CI->session->userdata('user');
			return $user['user_id'];
		}
	
	// --------------------------------------------------------------------
	
	/**
		* Gets email from session
		*
		* @access public
		* @return string
		*/
		function get_email()
		{
			$user = $this->CI->session->userdata('user');
			return $user['email'];
		}
		
	// --------------------------------------------------------------------

	/**
		* Checks if a username is already in the database
		*
		* @access public
		* @param string
		* @return boolean
		*/
		function check_username($username)
		{
			$this->CI->db->where('username', $username);
			$this->CI->db->from('users');
			return ($this->CI->db->count_all_results() > 0) ? TRUE : FALSE;
		}
		
	// --------------------------------------------------------------------

	/**
		* Checks if an email is already in the database
		*
		* @access public
		* @param string
		* @return boolean
		*/
		function check_email($email)
		{
			$this->CI->db->where('email', $email);
			$this->CI->db->from('users');
			return ($this->CI->db->count_all_results() > 0) ? TRUE : FALSE;
		}			
		
}
// END User Class

/* End of file User.php */
/* Location: ./system/application/libraries/User.php */
