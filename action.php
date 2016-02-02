<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_imageshack extends DokuWiki_Action_Plugin {

    /**
     * register the eventhandlers and initialize some options
     */
    function register(Doku_Event_Handler $controller){
        $controller->register_hook('MEDIAMANAGER_STARTED',
                                   'AFTER',
                                   $this,
                                   'handle_start',
                                   array());


        $controller->register_hook('MEDIAMANAGER_CONTENT_OUTPUT',
                                   'BEFORE',
                                   $this,
                                   'handle_output',
                                   array());
    }

    function handle_start(&$event, $param){
        if(!isset($_FILES['imageshack_file'])) return;

        if($_FILES['imageshack_file']['error'] ||
           !is_uploaded_file($_FILES['imageshack_file']['tmp_name'])){
            msg(sprintf('The was a problem receiving the file from you (error %d)',
                $_FILES['imageshack_file']['error']),-1);
            return;
        }

        require_once(DOKU_INC.'/inc/HTTPClient.php');
        $http = new HTTPClient();
        $http->timeout = 60;
        $http->headers['Content-Type'] = 'multipart/form-data';

        $data = array(
                    'xml'  => 'yes',
                    'fileupload' => array(
                            'filename' => $_FILES['imageshack_file']['name'],
                            'mimetype' => $_FILES['imageshack_file']['type'],
                            'body' => file_get_contents($_FILES['imageshack_file']['tmp_name'])
                        )
                );
        $xml = $http->post('http://imageshack.us/index.php',$data);

        if(!$xml){
            msg('There was a problem with uploading your file to imageshack: '.$http->error,-1);
            return;
        }

        $xml = new SimpleXMLElement($xml);
        if(!$xml){
            msg('ImageShack did not accept your upload',-1);
            return;
        }

        list($w,$h) = explode('x',(string) $xml->resolution[0]);
        $_SESSION['imageshack'][] = array(
            'link'   => (string) $xml->image_link[0],
            'adlink' => (string) $xml->ad_link[0],
            'name'   => (string) $xml->image_name[0],
            'width'  => $w,
            'height' => $h,
            'size'   => (string) $xml->filesize[0]
        );
    }

    function handle_output(&$event, $param){
        if($event->data['do'] != 'imageshack') return;
        global $lang;

        echo '<h1 id="media__ns">'.$this->getLang('name').'</h1>';
        echo '<p>'.$this->getLang('intro').'</p>';
        echo '<form action="'.DOKU_BASE.'lib/exe/mediamanager.php" method="post" enctype="multipart/form-data">';
        echo '<input type="hidden" name="do" value="imageshack" />';
        echo '<input type="file" name="imageshack_file" />';
        echo '<input type="submit" value="'.$lang['btn_upload'].'" class="button" />';
        echo '</form>';

        // output the uploads stored in the current session
        if(is_array($_SESSION['imageshack'])){
            $files = array_reverse($_SESSION['imageshack']);

            $twibble = 1;
            foreach($files as $item){
                $twibble *= -1;
                $zebra = ($twibble == -1) ? 'odd' : 'even';
                list($ext,$mime,$dl) = mimetype($item['name']);
                $class = preg_replace('/[^_\-a-z0-9]+/i','_',$ext);
                $class = 'select mediafile mf_'.$class;

                echo '<div class="'.$zebra.'">'.NL;
                echo '<a name="h_'.$item['link'].'" class="'.$class.'">'.hsc($item['name']).'</a> ';
                echo '<span class="info">('.$item['width'].'&#215;'.$item['height'].' '.filesize_h($item['size']).')</span>'.NL;
                echo ' <a href="'.$item['adlink'].'" target="_blank"><img src="'.DOKU_BASE.'lib/images/magnifier.png" '.
                     'alt="'.$lang['mediaview'].'" title="'.$lang['mediaview'].'" class="btn" /></a>'.NL;
                echo '<div class="example" id="ex_'.str_replace(':','_',$item['link']).'">';
                echo $lang['mediausage'].' <code>{{'.hsc($item['link']).'}}</code>';
                echo '</div>';

                if($item['width'] > 120 || $item['height'] > 100){
                    $w = 120;
                    $h = 100;
                }else{
                    $w = $item['width'];
                    $h = $item['height'];
                }

                $src = ml($item['link'],array('w'=>$w,'h'=>$h));
                $p = array();
                $p['width']  = $w;
                $p['height'] = $h;
                $p['alt']    = $item['name'];
                $p['class']  = 'thumb';
                $att = buildAttributes($p);

                // output
                echo '<div class="detail">';
                echo '<div class="thumb">';
                echo '<a name="d_'.$item['link'].'" class="select">';
                echo '<img src="'.$src.'" '.$att.' />';
                echo '</a>';
                echo '</div>';
                echo '</div>';

                echo '<div class="clearer"></div>'.NL;
                echo '</div>'.NL;


            }

        }
        $event->preventDefault();
    }


}

