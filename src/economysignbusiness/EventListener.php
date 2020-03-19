<?php

namespace economysignbusiness;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\tile\Sign;
use pocketmine\scheduler\Task;

use economysignbusiness\utils\API;
use economysignbusiness\utils\NameManager;
use onebone\economyapi\EconomyAPI;

use Jpnlibrary\JpnLibrary;

class EventListener implements Listener
{
	
	public $cooltime;

    public function __construct($owner)
    {
        $this->owner = $owner;
    }


    public function onTap(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();
	$name = $player->getName();
        if (!in_array($block->getId(), API::BLOCK_SIGN)) return;
        $tile = $player->getLevel()->getTile($block);
        if ($tile instanceof Sign) {
            $line = $tile->getText();
            if (!isset($line[0])) return;
            $tag = $line[0];
            if ($tag !== API::PURCHASE_TAG && $tag !== API::SELL_TAG && $tag !== API::EXCHANGE_TAG) {
                return;
            }
            $unit = EconomyAPI::getInstance()->getMonetaryUnit();
			
	        if (!isset($this->cooltime[$name])) {
                $this->checkDoProgress($player, $block, $name);
                return;
            }
		if ($block->asVector3() != $this->cooltime[$name]) {
                $this->checkDoProgress($player, $block, $name);
                return;
            }
            unset($this->cooltime[$name]);
            
            switch ($line[0]) {
                case API::PURCHASE_TAG:
                    $this->getApi()->purchaseItem($player, $block);
                    break;

                case API::SELL_TAG:
                    $this->getApi()->sellItem($player, $block);
                    break;

                case API::EXCHANGE_TAG:
                    $this->getApi()->exchangeItem($player, $block);
                    break;
            }
        }
    }


    public function onBreak(BlockBreakEvent $event)
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if (!in_array($block->getId(), API::BLOCK_SIGN)) return;
        $tile = $player->getLevel()->getTile($block);
        if ($tile instanceof Sign) {
            $line = $tile->getText();
            if (!isset($line[0])) return;
            $tag = $line[0];
            if ($tag !== API::PURCHASE_TAG && $tag !== API::SELL_TAG && $tag !== API::EXCHANGE_TAG) {
                return;
            }
            if (!$player->isOp()) {
            	$player->sendMessage("§b【運営】 >> §c削除できる権限がありません");
            	$event->setCancelled();
            	return;
            }
            switch ($line[0]) {
                case API::PURCHASE_TAG:
                case API::SELL_TAG:
                case API::EXCHANGE_TAG:
                    $this->getProvider()->removeShopData($block);
                    $player->sendMessage("§b【運営】 >> §e削除しました");
                    break;
            }
        }
    }


    public function onChange(SignChangeEvent $event)
    {
        $player = $event->getPlayer();
        $line = $event->getLines();
        if (empty($line[0])) return;
        if (empty($line[1])) return;
        if (empty($line[2])) return;
        if (!in_array($line[0], API::REQUIRE_FIRST_LINE)) return;
        if (!$player->isOp()) {
            $player->sendMessage("§b【運営】 >> §c製作できる権限がありません");
            return;
        }
        switch ($line[0]) {
            case "buy":
            case "purchase":
                if (empty($line[3])) return;
                $item = explode(":", $line[1]);
                if (count($item) == 1) $item[1] = 0;
                if (!ctype_digit($item[0])) {
                    $player->sendMessage("§b【運営】 >> §cID(数字)を書き込んでください");
                    return;
                }
                //$itemName = Item::get((int)$item[0], (int)$item[1])->getName();
	        $itemName = JpnLibrary::getInstance()->getJpnName("{$item[0]}:{$item[1]}");
                if (!ctype_digit($line[2])) {
                    $player->sendMessage("§b【運営】 >> §c数値を書き込んでください");
                    return;
                }
                $amount = (int) $line[2];
                $unit = EconomyAPI::getInstance()->getMonetaryUnit();
                if (!ctype_digit($line[3])) {
                    $player->sendMessage("§b【運営】 >> §c数値を書き込んでください");
                    return;
                }
                $price = (int) $line[3];
                $event->setLine(0, API::PURCHASE_TAG);
                $event->setLine(1, "§l".$itemName);
                $event->setLine(2, "§l".$amount);
                $event->setLine(3, "§l".$unit.$price);
                $this->getProvider()->setShopDataOfSellAndPurchase($event->getBlock(), $item[0], $item[1], $amount, $price);
                $player->sendMessage("§b【運営】 >> §e販売看板を作りました");
                break;

            case "sell":
                if (empty($line[3])) return;
                $item = explode(":", $line[1]);
                if (count($item) == 1) $item[1] = 0;
                if ($item[1] == null) {
                    $player->sendMessage("§b【運営】 >> §cしっかりとID:METAの形で書き込んでください");
                    return;
                }
                //$itemName = Item::get((int)$item[0], (int)$item[1])->getName();
		$itemName = JpnLibrary::getInstance()->getJpnName("{$item[0]}:{$item[1]}");
                if (!ctype_digit($line[2])) {
                    $player->sendMessage("§b【運営】 >> §c数値を書き込んでください");
                    return;
                }
                $amount = (int) $line[2];
                $unit = EconomyAPI::getInstance()->getMonetaryUnit();
                if (!ctype_digit($line[3])) {
                    $player->sendMessage("§b【運営】 >> §c数値を書き込んでください");
                    return;
                }
                $price = (int) $line[3];
                $event->setLine(0, API::SELL_TAG);
                $event->setLine(1, "§l".$itemName);
                $event->setLine(2, "§l".$amount);
                $event->setLine(3, "§l".$unit.$price);
                $this->getProvider()->setShopDataOfSellAndPurchase($event->getBlock(), $item[0], $item[1], $amount, $price);
                $player->sendMessage("§b【運営】 >> §e売却看板を作りました");
                break;
        }
    }

    public function getServer()
    {
        return $this->owner->getServer();
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function getApi()
    {
        return $this->owner->api;
    }

    public function getProvider()
    {
        return $this->owner->provider;
    }
	
    public function checkDoProgress($player, $block, $name)
    {
	$xyz = $this->getProvider()->getCoordinate($block);
        $data = $this->getProvider()->getShopData($xyz);

        $player->sendMessage("§b【運営】 >> §e{$data["COUNT"]}個 {$data["PRICE"]}円 です");
        $player->sendMessage("§b【運営】 >> §eよろしければ もう一度タッチしてください");
	    
	$this->cooltime[$name] = $block->asVector3();
        $handler = $this->owner->getScheduler()->scheduleDelayedTask(
            new class($this->owner, $name) extends Task
            {
                function __construct($owner, $name)
                {
                    $this->owner = $owner;
		    $this->name = $name;
                }

                function onRun(int $tick)
                {
		unset($this->cooltime[$this->name]);
                }
            }, 3*20
        );
}
}
