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
  public $task = 'around_puzzle';

  function init()
  {

    $this->rc = rcmail::get_instance();
    $this->register_task('around_puzzle');
    // register actions
    $this->register_action('around_puzzle', array($this, 'index'));
    $this->register_action('mailbox_list', array($this, 'mailbox_list'));
    $this->register_action('preview', array($this, 'preview'));

    $this->add_hook('render_response', array($this, 'render_response'));
  }

  function index()
  {
    //$this->rc->output->send('around_puzzle.index');
    $this->to_render = array("testando" => "funciona!");
  }

  function preview(){
    $RCMAIL = $this->rc;
    $RCMAIL->storage_connect();
    $mbox       = rcube_utils::get_input_value('mbox', rcube_utils::INPUT_GET);
    $uid       = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GET);
    $RCMAIL->storage->set_folder('INBOX');
    $MESSAGE = new rcube_message($uid);

    $this->to_render = $MESSAGE;
  }

  function mailbox_list()
  {

    $RCMAIL = $this->rc;
    $RCMAIL->storage_connect();
    $RCMAIL->storage->set_folder('INBOX');
    $RCMAIL->storage->set_page(1);
    $save_arr      = array();
    $dont_override = (array) $RCMAIL->config->get('dont_override');

    $mbox_name = $RCMAIL->storage->get_folder();
    $threading = (bool) $RCMAIL->storage->get_threading();
    $RCMAIL->storage->set_folder($_SESSION['mbox'] = $mbox);
    $RCMAIL->storage->set_page($_SESSION['page'] = $page);
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
    $render['a_headers_count'] = count($a_headers);

    $list = array();

    foreach ($a_headers as &$header) {
      $list[] = (object) array(
        'uid' => $header->uid,
        'subject' => rcube_mime::decode_header($header->subject, $header->charset),
        'from' => rcube_mime::decode_header($header->from, $header->charset),
        'to' => rcube_mime::decode_header($header->to, $header->charset),
        // 'cc' => rcube_mime::decode_header($header->cc, $header->charset),
        // 'replayto' => rcube_mime::decode_header($header->replayto, $header->charset),
        // 'in_replay_to' => rcube_mime::decode_header($header->in_replay_to, $header->charset),
        'date' => $header->date,
        'size' => $header->size,
        'internaldate' => $header->internaldate,
        'priority' => $header->priority,
        'folder' => $header->folder,
        'flags' => $header->flags
      );
    }
    $render['headers'] = $list;


    $this->to_render = $render;
  }


  function render_response($args){
    header('Content-Type: application/json; charset=' . $this->rc->output->get_charset());
    echo @json_encode($this->to_render);
    exit;
  }
}
