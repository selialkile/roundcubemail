<?php

namespace Puzzle\Message;

class Preview
{
  private $rcmail;

    function __construct($rcmail, $uid, $mbox)
    {
        $this->rcmail = $rcmail;
        $this->uid = $uid;
        $this->mbox = $mbox;
    }

    public function to_json()
    {
        $this->rcmail->storage->set_folder('INBOX');
        $this->message = new \rcube_message($this->uid);
        $msg = new \stdClass();
        $msg->headers = $this->message->headers;
        $msg->full_headers = $this->full_headers();
        $msg->body = $this->body();
        return $msg;
    }

    function full_headers()
    {
        $source = $this->rcmail->storage->get_raw_headers($this->uid);

        if ($source !== false) {
            $source = trim(\rcube_charset::clean($source));
            $source = htmlspecialchars($source);
            $source = preg_replace(
                array(
                    '/\n[\t\s]+/',
                    '/^([a-z0-9_:-]+)/im',
                    '/\r?\n/'
                ),
                array(
                    "\n&nbsp;&nbsp;&nbsp;&nbsp;",
                    '<font class="bold">\1</font>',
                    '<br />'
                ), $source);

            return $source;
        }else{
            return "";
        }
    }

    function body()
    {

        $MESSAGE = &$this->message;
        $RCMAIL = &$this->rcmail;

        if (!is_array($MESSAGE->parts) && empty($MESSAGE->body)) {
            return '';
        }

        if (!$attrib['id'])
            $attrib['id'] = 'rcmailMsgBody';

        $safe_mode = $MESSAGE->is_safe || intval($_GET['_safe']);
        $out = '';

        $header_attrib = array();
        foreach ($attrib as $attr => $value) {
            if (preg_match('/^headertable([a-z]+)$/i', $attr, $regs)) {
                $header_attrib[$regs[1]] = $value;
            }
        }

        if (!empty($MESSAGE->parts)) {
            foreach ($MESSAGE->parts as $part) {
                if ($part->type == 'headers') {
                    $out .= \html::div('message-partheaders', rcmail_message_headers(sizeof($header_attrib) ? $header_attrib : null, $part->headers));
                }
                else if ($part->type == 'content') {
                    // unsupported (e.g. encrypted)
                    if ($part->realtype) {
                        if ($part->realtype == 'multipart/encrypted' || $part->realtype == 'application/pkcs7-mime') {
                            $out .= \html::span('part-notice', $RCMAIL->gettext('encryptedmessage'));
                        }
                        continue;
                    }
                    else if (!$part->size) {
                        continue;
                    }

                    // Check if we have enough memory to handle the message in it
                    // #1487424: we need up to 10x more memory than the body
                    else if (!\rcube_utils::mem_check($part->size * 10)) {
                        $out .= \html::span('part-notice', $RCMAIL->gettext('messagetoobig'). ' '
                            . \html::a('?_task=mail&_action=get&_download=1&_uid='.$MESSAGE->uid.'&_part='.$part->mime_id
                                .'&_mbox='. urlencode($MESSAGE->folder), $RCMAIL->gettext('download')));
                        continue;
                    }

                    // fetch part body
                    $body = $MESSAGE->get_part_body($part->mime_id, true);

                    // extract headers from message/rfc822 parts
                    if ($part->mimetype == 'message/rfc822') {
                        $msgpart = \rcube_mime::parse_message($body);
                        if (!empty($msgpart->headers)) {
                            $part = $msgpart;
                            $out .= \html::div('message-partheaders', rcmail_message_headers(sizeof($header_attrib) ? $header_attrib : null, $part->headers));
                        }
                    }

                    // message is cached but not exists (#1485443), or other error
                    if ($body === false) {
                        rcmail_message_error($MESSAGE->uid);
                    }

                    $plugin = $this->rcmail->plugins->exec_hook('message_body_prefix',
                        array('part' => $part, 'prefix' => ''));

                    $body = $this->rcmail_print_body($body, $part, array('safe' => $safe_mode, 'plain' => !$RCMAIL->config->get('prefer_html')));

                    if ($part->ctype_secondary == 'html') {
                        $body     = \rcmail_html4inline($body, $attrib['id'], 'rcmBody', $attrs, $safe_mode);
                        $div_attr = array('class' => 'message-htmlpart');
                        $style    = array();

                        if (!empty($attrs)) {
                            foreach ($attrs as $a_idx => $a_val)
                                $style[] = $a_idx . ': ' . $a_val;
                            if (!empty($style))
                                $div_attr['style'] = implode('; ', $style);
                        }

                        $out .= html::div($div_attr, $plugin['prefix'] . $body);
                    }
                    else
                        $out .= html::div('message-part', $plugin['prefix'] . $body);
                }
            }
        }
        else {
            // Check if we have enough memory to handle the message in it
            // #1487424: we need up to 10x more memory than the body
            if (!rcube_utils::mem_check(strlen($MESSAGE->body) * 10)) {
                $out .= html::span('part-notice', $RCMAIL->gettext('messagetoobig'). ' '
                    . html::a('?_task=mail&_action=get&_download=1&_uid='.$MESSAGE->uid.'&_part=0'
                        .'&_mbox='. urlencode($MESSAGE->folder), $RCMAIL->gettext('download')));
            }
            else {
                $plugin = $RCMAIL->plugins->exec_hook('message_body_prefix',
                    array('part' => $MESSAGE, 'prefix' => ''));

                $out .= html::div('message-part',
                    $plugin['prefix'] . rcmail_plain_body($MESSAGE->body));
            }
        }

        // list images after mail body
        if ($RCMAIL->config->get('inline_images', true) && !empty($MESSAGE->attachments)) {
            $thumbnail_size   = $RCMAIL->config->get('image_thumbnail_size', 240);
            $client_mimetypes = (array)$RCMAIL->config->get('client_mimetypes');

            foreach ($MESSAGE->attachments as $attach_prop) {
                // skip inline images
                if ($attach_prop->content_id && $attach_prop->disposition == 'inline') {
                    continue;
                }

                // Content-Type: image/*...
                if ($mimetype = rcmail_part_image_type($attach_prop)) {
                    // display thumbnails
                    if ($thumbnail_size) {
                        $show_link = array(
                            'href'    => $MESSAGE->get_part_url($attach_prop->mime_id, false),
                            'onclick' => sprintf(
                                'return %s.command(\'load-attachment\',\'%s\',this)',
                                rcmail_output::JS_OBJECT_NAME,
                                $attach_prop->mime_id)
                        );
                        $out .= html::p('image-attachment',
                            html::a($show_link + array('class' => 'image-link', 'style' => sprintf('width:%dpx', $thumbnail_size)),
                                html::img(array(
                                    'class' => 'image-thumbnail',
                                    'src'   => $MESSAGE->get_part_url($attach_prop->mime_id, 'image') . '&_thumb=1',
                                    'title' => $attach_prop->filename,
                                    'alt'   => $attach_prop->filename,
                                    'style' => sprintf('max-width:%dpx; max-height:%dpx', $thumbnail_size, $thumbnail_size),
                                ))
                            ) .
                            html::span('image-filename', rcube::Q($attach_prop->filename)) .
                            html::span('image-filesize', rcube::Q($RCMAIL->message_part_size($attach_prop))) .
                            html::span('attachment-links',
                                (in_array($mimetype, $client_mimetypes) ? html::a($show_link, $RCMAIL->gettext('showattachment')) . '&nbsp;' : '') .
                                    html::a($show_link['href'] . '&_download=1', $RCMAIL->gettext('download'))
                            ) .
                            html::br(array('style' => 'clear:both'))
                        );
                    }
                    else {
                        $out .= html::tag('fieldset', 'image-attachment',
                            html::tag('legend', 'image-filename', rcube::Q($attach_prop->filename)) .
                            html::p(array('align' => 'center'),
                                html::img(array(
                                    'src'   => $MESSAGE->get_part_url($attach_prop->mime_id, 'image'),
                                    'title' => $attach_prop->filename,
                                    'alt'   => $attach_prop->filename,
                            )))
                        );
                    }
                }
            }
        }

        // tell client that there are blocked remote objects
        if ($REMOTE_OBJECTS && !$safe_mode) {
            $OUTPUT->set_env('blockedobjects', true);
        }

        return html::div($attrib, $out);

    }

    function rcmail_print_body($body, $part, $p = array())
    {
        global $RCMAIL;

        // trigger plugin hook
        $data = $RCMAIL->plugins->exec_hook('message_part_before',
            array('type' => $part->ctype_secondary, 'body' => $body, 'id' => $part->mime_id)
                + $p + array('safe' => false, 'plain' => false, 'inline_html' => true));

        // convert html to text/plain
        if ($data['plain'] && ($data['type'] == 'html' || $data['type'] == 'enriched')) {
            if ($data['type'] == 'enriched') {
                $data['body'] = rcube_enriched::to_html($data['body']);
            }

            $txt  = new \rcube_html2text($data['body'], false, true);
            $body = $txt->get_text();
            $part->ctype_secondary = 'plain';
        }
        // text/html
        else if ($data['type'] == 'html') {
            $body = \rcmail_wash_html($data['body'], $data, $part->replaces);
            $part->ctype_secondary = $data['type'];
        }
        // text/enriched
        else if ($data['type'] == 'enriched') {
            $body = \rcube_enriched::to_html($data['body']);
            $body = \rcmail_wash_html($body, $data, $part->replaces);
            $part->ctype_secondary = 'html';
        }
        else {
            // assert plaintext
            $body = $data['body'];
            $part->ctype_secondary = $data['type'] = 'plain';
        }

        // free some memory (hopefully)
        unset($data['body']);

        // plaintext postprocessing
        if ($part->ctype_secondary == 'plain') {
            $body = rcmail_plain_body($body, $part->ctype_parameters['format'] == 'flowed');
        }

        // allow post-processing of the message body
        $data = $RCMAIL->plugins->exec_hook('message_part_after',
            array('type' => $part->ctype_secondary, 'body' => $body, 'id' => $part->mime_id) + $data);

        return $data['body'];
    }
}