<?php

/**
 * Around Puzzle
 *
 * Api and JS libs for front-end
 *
 * @version 0.1
 * @license GNU GPLv3+
 * @author Thiago Coutinho
 */
class around_puzzle extends rcube_plugin
{
  public $task = 'mail|around_puzzle';

  function init()
  {
    $this->rc = rcmail::get_instance();
    $this->register_task('around_puzzle');
    // register actions
    $this->register_action('around_puzzle', array($this, 'index'));
    $this->register_action('mailbox_list', array($this, 'mailbox_list'));

    $this->add_hook('render_response', array($this, 'render_response'));
  }

  function index()
  {
    //$this->rc->output->send('around_puzzle.index');
    $this->to_render = array("foda" => "blabalblabal");
  }

  function mailbox_list()
  {

    $RCMAIL = $this->rc;
    $RCMAIL->storage_connect();

    $save_arr      = array();
    $dont_override = (array) $RCMAIL->config->get('dont_override');

    // is there a sort type for this request?
    if ($sort = rcube_utils::get_input_value('_sort', rcube_utils::INPUT_GET)) {
        // yes, so set the sort vars
        list($sort_col, $sort_order) = explode('_', $sort);

        // set session vars for sort (so next page and task switch know how to sort)
        if (!in_array('message_sort_col', $dont_override)) {
            $_SESSION['sort_col'] = $save_arr['message_sort_col'] = $sort_col;
        }
        if (!in_array('message_sort_order', $dont_override)) {
            $_SESSION['sort_order'] = $save_arr['message_sort_order'] = $sort_order;
        }
    }

    $mbox_name = $RCMAIL->storage->get_folder();
    $threading = (bool) $RCMAIL->storage->get_threading();

    // Synchronize mailbox cache, handle flag changes
    $RCMAIL->storage->folder_sync($mbox_name);

    // fetch message headers
    if ($count = $RCMAIL->storage->count($mbox_name, $threading ? 'THREADS' : 'ALL', !empty($_REQUEST['_refresh']))) {
        $a_headers = $RCMAIL->storage->list_messages($mbox_name, NULL, 'DATE', 'DESC');
    }

    // update message count display
    $pages  = ceil($count/$RCMAIL->storage->get_pagesize());
    $page   = $count ? $RCMAIL->storage->get_page() : 1;
    $exists = $RCMAIL->storage->count($mbox_name, 'EXISTS', true);

    $render['messagecount'] = $count;
    $render['pagecount'] = $pages;
    $render['threading'] = $threading;
    $render['current_page'] = $page;
    $render['exists'] = $exists;


    $this->to_render = $render;
  }


  function render_response($args){
    $args['response'] =  $this->to_render;
    return $args;
  }
}
