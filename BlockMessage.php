<?php
require_once "../vendor/autoload.php";
require_once "../tw-config-v2.php";
require_once "../openAI-config.php";

use Abraham\TwitterOAuth\TwitterOAuth;

class BlockMessage
{
    var $connection = null;

    public function __construct()
    {
        $this->twitterConnection();
        $result = $this->getTwitterDM();
        if(!$this->judgmentSpam($result[0])){
            $this->setTwitterBlockedUser($result[1],$result[2]);
        }
    }

    public function twitterConnection()
    {
        // TwitterOAuthクラスのインスタンスを作成する
        $this->connection = new TwitterOAuth(APIKEY, APISECRET, ACCESSTOKEN, ACCESSTOKENSECRET);
        // APIバージョンを設定する
        $this->connection->setApiVersion('2');
    }

    /**
     * DMを取得する
     * @return array 
     */
    public function getTwitterDM()
    {
        $dm_events = $this->connection->get('dm_events',["expansions"=>"sender_id","user.fields"=>"username,id"]);
        return [$dm_events->data[0]->text,$dm_events->data[0]->sender_id,$dm_events->data[0]->id];
    }
    /**
     * OpenAI APIを使用してスパム判定
     * @param string $text
     * @return false
     */
    public function judgmentSpam(string $text="")
    {
        if (!$text) return false;
        $client = OpenAI::client(OPENAIAPIKEY);
        $result = $client->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ["role"=>"system", "content"=>'Your job is to identify spam, please determine if the message is spam or not. If it is spam, please write "This is spam". For non-spam messages, write "OK".'],
                ["role"=>"system", "content"=>'respond to in English.'],
                ['role'=> 'user', 'content' => $text],
            ]
        ]);
        if(preg_match("/(OK)/i",$result->choices[0]->message->content)){
            print "non-spam".PHP_EOL;
            return true;    
        }
        if(preg_match("/(spam)/i",$result->choices[0]->message->content)){
            print "This is spam".PHP_EOL;
            return false;
        }
        return true;
    }

    /**
     * スパムアカウントをブロック
     * @param string $blocked_user_id
     * @return false
     */
    public function setTwitterBlockedUser(string $blocked_user_id = "")
    {
        if (!$blocked_user_id) return false;

        $block_result = $this->connection->post('users/'.MYTWID.'/blocking', ['target_user_id' => $blocked_user_id], true);
        if ($block_result->data->blocking) {
            print 'Blocked user successfully.'.PHP_EOL;
            return true;
        } else {
            print 'Failed to block user.'.PHP_EOL;
            return false;
        }
    }
}
if($argv[1]==="spamcheck"){
    new BlockMessage();
}
