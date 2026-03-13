<?php

namespace BedrockExplode;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\block\VanillaBlocks;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener{

    private Config $config;

    private array $activeTNT = [];
    private array $bedrockDamage = [];

    public function onEnable() : void{
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onPlace(BlockPlaceEvent $event) : void{

        $player = $event->getPlayer();

        if(!$player->hasPermission("bedrockexplode.use")){
            return;
        }

        foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){

            if($block->getTypeId() === VanillaBlocks::TNT()->getTypeId()){

                if(isset($this->activeTNT[$player->getName()])){
                    $event->cancel();
                    $player->sendMessage($this->config->getNested("messages.already-active"));
                    return;
                }

                $this->activeTNT[$player->getName()] = true;
                $player->sendMessage($this->config->getNested("messages.place-tnt"));
            }
        }
    }

    public function onExplode(EntityExplodeEvent $event) : void{

        $required = $this->config->get("tnt-required");

        foreach($this->activeTNT as $playerName => $v){

            $player = $this->getServer()->getPlayerExact($playerName);

            if($player === null){
                unset($this->activeTNT[$playerName]);
                continue;
            }

            $bedrockHit = false;

            foreach($event->getBlockList() as $block){

                if($block->getTypeId() === VanillaBlocks::BEDROCK()->getTypeId()){

                    $bedrockHit = true;

                    $pos = $block->getPosition();
                    $key = $pos->getX().":".$pos->getY().":".$pos->getZ();

                    if(!isset($this->bedrockDamage[$key])){
                        $this->bedrockDamage[$key] = 0;
                    }

                    $this->bedrockDamage[$key]++;

                    $remaining = $required - $this->bedrockDamage[$key];

                    if($this->bedrockDamage[$key] >= $required){

                        $pos->getWorld()->setBlock($pos, VanillaBlocks::AIR());
                        unset($this->bedrockDamage[$key]);

                        $player->sendMessage($this->config->getNested("messages.success"));

                    }else{

                        $msg = str_replace(
                            "{remaining}",
                            $remaining,
                            $this->config->getNested("messages.progress")
                        );

                        $player->sendMessage($msg);
                    }
                }
            }

            if(!$bedrockHit){
                $player->sendMessage($this->config->getNested("messages.not-touching"));
            }

            unset($this->activeTNT[$playerName]);
        }
    }
}
