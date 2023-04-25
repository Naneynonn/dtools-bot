<?php

use Discord\Parts\Interactions\Interaction;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Embed\Embed;

use Naneynonn\Language;

use ByteUnits\Metric;

$discord->listenCommand('help', function (Interaction $interaction) use ($discord) {
  $lng = new Language(lang: $interaction->locale);

  $embed = $discord->factory(Embed::class)
    ->setTitle($lng->get('embeds.help.title'))
    ->setColor($lng->get('color.default'))
    ->setDescription(sprintf($lng->get('embeds.help.description'), $lng->get('site'), $lng->get('support')))
    ->addField(['name' => $lng->get('embeds.help.settings'), 'value' => $lng->get('commands.automod'), 'inline' => 'false'])
    ->addField(['name' => $lng->get('embeds.filters'), 'value' => $lng->get('commands.filters'), 'inline' => 'false'])
    // ->addField(['name' => $lng->get('embeds.info'), 'value' => $lng->get('embeds.help.info'), 'inline' => 'false'])
    ->setThumbnail('https://cdn.discordapp.com/attachments/525360788924399637/1099658819023421470/favicon.png');

  $interaction->respondWithMessage(builder: MessageBuilder::new()->addEmbed($embed), ephemeral: true);

  echo "[-] Command | HELP: " . Metric::bytes(memory_get_usage())->format();
});
