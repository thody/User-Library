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

	// Private variables.  Do not change!
	var $CI;
	var $_user;
	
	/**
	 * User Class Constructor
	 *
	 * The constructor loads the Session class, used to store the user info.
	 */		
	function User($params = array())
	{	
		// Set the super object to a local variable for use later
		$this->CI =& get_instance();
		
		// Are any config settings being passed manually?  If so, set them
		$config = array();
		if (count($params) > 0)
		{
			foreach ($params as $key => $val)
			{
				$config[$key] = $val;
			}
		}
		
		// Load the Sessions class
		$this->CI->load->database();
		$this->CI->load->library('session', $config);
			
		// Grab the user data array from the session table, if it exists
		if ($this->CI->session->userdata('user') !== FALSE)
		{
			$this->_user = $this->CI->session->userdata('user');
		}
	
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
		$this->CI->db->where('id', $user_id);
		return $this->CI->db->update('users', $user);
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
		if (is_numeric($identifier))
		{
			$field = 'user_id';
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
	function login($username = null, $password = null)
	{		
		// Look for valid user
		$user = $this->_test_user_credentials($username, $password);

		// Handle failed login
		if ( ! $user )
		{
			return FALSE;
		}
		
		$this->_set_user_session($user);
		
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
		$this->CI->db->where('username', $username);
		$this->CI->db->where('password', $this->_salt($password));
		$this->CI->db->from('users');
		
		if ($this->CI->db->count_all_results() > 0)
		{
			$this->CI->db->select('id, username, email');
			$this->CI->db->where('username', $username);
			$this->CI->db->where('password', $this->_salt($password));
			$query = $this->CI->db->get('users');
			return $query->row_array();
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
		if ( true )
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
	 * Logout current user
	 *
	 * @access	public
	 * @return	bool
	 */
	function logout()
	{
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
		if(is_array($this->CI->session->userdata('user')))
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
		
}
// END User Class

/* End of file User.php */
/* Location: ./system/application/libraries/User.php */