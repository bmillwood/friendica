<?php
/**
 * Copyright (C) 2010-2024, the Friendica project
 * SPDX-FileCopyrightText: 2010-2024 the Friendica project
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * contain methods to deal with images
 */

use Friendica\DI;

/**
 * This class contains methods to deal with images
 */
class Image
{
	/**
	 * Give all available options for the background image
	 *
	 * @param array $arr Array with the present user settings
	 * @return array Array with the image options
	 */
	public static function get_options(array $arr): array
	{
		$bg_image_options = [
			'stretch' => ['frio_bg_image_option', DI::l10n()->t('Top Banner'), 'stretch', DI::l10n()->t('Resize image to the width of the screen and show background color below on long pages.'), ($arr['bg_image_option'] == 'stretch')],
			'cover'   => ['frio_bg_image_option', DI::l10n()->t('Full screen'), 'cover', DI::l10n()->t('Resize image to fill entire screen, clipping either the right or the bottom.'), ($arr['bg_image_option'] == 'cover')],
			'contain' => ['frio_bg_image_option', DI::l10n()->t('Single row mosaic'), 'contain', DI::l10n()->t('Resize image to repeat it on a single row, either vertical or horizontal.'), ($arr['bg_image_option'] == 'contain')],
			'repeat'  => ['frio_bg_image_option', DI::l10n()->t('Mosaic'), 'repeat', DI::l10n()->t('Repeat image to fill the screen.'), ($arr['bg_image_option'] == 'repeat')],
		];

		return $bg_image_options;
	}
}
