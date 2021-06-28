<?php


namespace CupidonSauce173\PigraidNotifications\Utils;


use CupidonSauce173\PigraidNotifications\NotifLoader;
use CupidonSauce173\PigraidNotifications\Object\Notification;
use CupidonSauce173\PigraidNotifications\task\MySQLThread;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

use function array_search;
use function implode;
use function explode;
use function strpos;
use function str_replace;
use function sort;

class API
{
    /**
     * @param Player $player
     * @param string $langKey
     * @param string $event
     * @param array|null $varKeys
     */
    public function createNotification(Player $player, string $langKey, string $event, array $varKeys = null): void
    {
        # Note, varKeys build = array ( 0 => "sender|server", 1 => "sender|friend1" )
        $target = $player->getName();
        if($varKeys !== null){
            $keys = implode(',', $varKeys);
            $query = "INSERT INTO notifications (player,langkey,VarKeys,event) VALUES ('$target','$langKey','$keys','$event')";
        }else{
            $query = "INSERT INTO notifications (player,langKey,event) VALUES ('$target','$langKey','$event')";
        }
        $thread = new MySQLThread($query, NotifLoader::getInstance()->DBInfo);
        $thread->start();
    }

    /**
     * @param Notification $notification
     */
    public function deleteNotification(Notification $notification): void
    {
        $id = (int)$notification->getId();
        $key = array_search($notification, NotifLoader::getInstance()->notificationList[$notification->getPlayer()], true);
        $user = $notification->getPlayer();
        unset(NotifLoader::getInstance()->notificationList[$user][$key]);
        sort(NotifLoader::getInstance()->notificationList[$user]);
        $thread = new MySQLThread("DELETE FROM notifications WHERE id = $id", NotifLoader::getInstance()->DBInfo);
        $thread->start();
    }

    /**
     * @param array $notificationList
     */
    public function deleteNotifications(array $notificationList): void
    {
        $ids = [];
        /** @var Notification $notif */
        foreach ($notificationList as $notif) {
            $ids[] = $notif->getId();
            NotifLoader::getInstance()->notificationList[$notif->getPlayer()] = [];
        }
        $ids = implode("','", $ids);
        $thread = new MySQLThread("DELETE FROM notifications WHERE id IN ('$ids')", NotifLoader::getInstance()->DBInfo);
        $thread->start();
    }

    /**
     * @param Notification $notification
     * @return string
     */
    public function TranslateNotification(Notification $notification): string
    {
        $keys = [];
        foreach ($notification->getVarKeys() as $key) {
            $values = explode('|', $key);
            $keys[$values[0]] = $values[1];
        }

        $message = $this->GetText($notification->getLangKey());
        if ($message === null) {
            NotifLoader::getInstance()->getLogger()->alert('langKey: ' . $notification->getLangKey() . ' is not found in the Language File. Stopping the translation.');
            return 'Error while translating';
        }
        foreach ($keys as $key => $value) {
            if (strpos($message, '%' . $key . '%') !== false) {
                $message = str_replace('%' . $key . '%', $value, $message);
            } else {
                $message = 'Unknown Index: ' . $key . ' with ' . $value . ' as value.';
            }
        }
        $message = NotifLoader::getInstance()->config['prefix'] . TextFormat::RESET . $message;
        return $message;
    }

    /**
     * @param string $message
     * @param array|null $LangKey
     * @return string|null
     */
    public function GetText(string $message, array $LangKey = null): ?string
    {
        if(!isset(NotifLoader::getInstance()->langKeys[$message])) return null;
        $text = NotifLoader::getInstance()->langKeys[$message];
        if ($LangKey !== null) {
            $text = str_replace($LangKey[0], $LangKey[1], $text);
        }
        return $text;
    }
}