<?php
  /** Directory with pictures. */
  $conf['dir'] = './pictures/';
  /** Directory for caching thumbnails (must be writeable!).*/
  $conf['cache'] = './cache/';
  /** URL to default album and picture icon. May be absolute or relative. */
  $conf['defaultIcon'] = '?static=defico';
  /** Name of file with definition of title image. */
  $conf['icotitlefname'] = '000.nfo';
  /** Name of file with defined usernames/passwords for locked/private albums. */
  $conf['lockfname'] = '000.lock';
  /** Width of thumbnail. */
  $conf['thumb_x'] = 160;
  /** Height of thumbnail. */
  $conf['thumb_y'] = 120;
  /** Width of middle size picture - the view size. */
  $conf['middle_x'] = 800;
  /** Number of characters of shortened image title. */
  $conf['imgTitleLen'] = 16;
  /** Title of whole gallery. */
  $conf['galTitle'] = 'SiGal gallery';
  /** String shown in bottom of each page. Designed to some words about legal use of photos. */
  $conf['legal_notice'] = 'No photos can be distributted without written permission of their author (<a href="http://gimli2.gipix.net">Gimli2</a>).';
?>
