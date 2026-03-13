<?php

namespace BedrockExplode;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\block\VanillaBlocks;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    private Config $config;

    private array $activeTNT = [];
    private array $bedrockDamage = [];

    public function onEnable(): void{
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onPlace(BlockPlaceEvent $event): void{
        $player = $event->getPlayer();

        if(!$player->hasPermission("bedrockexplode.use")){
            return;
        }

        $block = $event->getBlock();

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

    public function onExplode(EntityExplodeEvent $event): void{

        foreach($this->activeTNT as $player => $v){
            unset($this->activeTNT[$player]);
        }

        $blocks = $event->getBlockList();
        $required = $this->config->get("tnt-required");

        foreach($blocks as $block){

            if($block->getTypeId() === VanillaBlocks::BEDROCK()->getTypeId()){

                $posKey = $block->getPosition()->getX().":".
                          $block->getPosition()->getY().":".
                          $block->getPosition()->getZ();

                if(!isset($this->bedrockDamage[$posKey])){
                    $this->bedrockDamage[$posKey] = 0;
                }

                $this->bedrockDamage[$posKey]++;

                $remaining = $required - $this->bedrockDamage[$posKey];

                if($this->bedrockDamage[$posKey] >= $required){

                    $block->getPosition()->getWorld()->setBlock(
                        $block->getPosition(),
                        VanillaBlocks::AIR()
                    );

                    unset($this->bedrockDamage[$posKey]);

                    foreach($this->getServer()->getOnlinePlayers() as $p){
                        $p->sendMessage($this->config->getNested("messages.success"));
                    }

                }else{

                    foreach($this->getServer()->getOnlinePlayers() as $p){

                        $msg = str_replace(
                            "{remaining}",
                            $remaining,
                            $this->config->getNested("messages.progress")
                        );

                        $p->sendMessage($msg);
                    }
                }
            }
        }
    }
}
