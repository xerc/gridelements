<?php

declare(strict_types=1);

namespace GridElementsTeam\Gridelements\Backend\ItemsProcFuncs;

/***************************************************************
 *  Copyright notice
 *  (c) 2013 Jo Hasenau <info@cybercraft.de>
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use GridElementsTeam\Gridelements\Backend\LayoutSetup;
use GridElementsTeam\Gridelements\Helper\GridElementsHelper;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class/Function which manipulates the item-array for table/field tt_content CType.
 *
 * @author Jo Hasenau <info@cybercraft.de>
 */
class ListTypeList implements SingletonInterface
{
    /**
     * @var LayoutSetup
     */
    protected LayoutSetup $layoutSetup;

    /**
     * ItemProcFunc for CType items
     *
     * @param array $params The array of parameters that is used to render the item list
     */
    public function itemsProcFunc(array &$params)
    {
        $this->init((int)$params['row']['pid']);

        if ((int)$params['row']['pid'] > 0) {
            if (isset($params['row']['colPos'])) {
                $colPos = is_array($params['row']['colPos']) ? ($params['row']['colPos'][0] ?? 0) : $params['row']['colPos'];
            } else {
                $colPos = 0;
            }
            $this->checkForAllowedListTypes(
                $params['items'],
                (int)($params['row']['pid'] ?? 0),
                (int)$colPos,
                (int)($params['row']['tx_gridelements_container'] ?? 0),
                (int)($params['row']['tx_gridelements_columns'] ?? 0)
            );
        } else {
            // negative uid_pid values indicate that the element has been inserted after an existing element
            // so there is no pid to get the backendLayout for and we have to get that first
            $existingElement = BackendUtility::getRecordWSOL('tt_content', -((int)$params['row']['pid']), 'pid,list_type,colPos,tx_gridelements_container,tx_gridelements_columns');
            if ($existingElement && (int)$existingElement['pid'] > 0) {
                $this->checkForAllowedListTypes(
                    $params['items'],
                    (int)($existingElement['pid'] ?? 0),
                    (int)($existingElement['colPos'] ?? 0),
                    (int)($existingElement['tx_gridelements_container'] ?? 0),
                    (int)($existingElement['tx_gridelements_columns'])
                );
            }
        }
    }

    /**
     * Checks if a ListType is allowed in this particular page or grid column - only this one column defines the allowed CTypes regardless of any parent column
     *
     * @param array $items The items of the current CType list
     * @param int $pageId The id of the page we are currently working on
     * @param int $pageColumn The page column the element is a child of
     * @param int $gridContainerId The ID of the current container
     * @param int $gridColumn The grid column the element is a child of
     */
    public function checkForAllowedListTypes(array &$items, int $pageId, int $pageColumn, int $gridContainerId, int $gridColumn)
    {
        if ($pageColumn >= 0 || $pageColumn === -2) {
            $column = $pageColumn ?: 0;
            $layout = GridElementsHelper::getSelectedBackendLayout($pageId);
        } else {
            $column = $gridColumn ?: 0;
            $gridElement = $this->layoutSetup->cacheCurrentParent($gridContainerId, true);
            $layout = $this->layoutSetup->getLayoutSetup($gridElement['tx_gridelements_backend_layout'] ?? '');
        }
        if (!empty($layout)) {
            $allowed = $layout['allowed'][$column]['list_type'] ?? [];
            $disallowed = $layout['disallowed'][$column]['list_type'] ?? [];
            if (!empty($allowed) || !empty($disallowed)) {
                foreach ($items as $key => $item) {
                    if (
                        (
                            !empty($allowed)
                            && !isset($allowed['*'])
                            && !isset($allowed[$item[1]])
                        ) || (
                            !empty($disallowed)
                            && (
                                isset($disallowed['*'])
                                || isset($disallowed[$item[1]])
                            )
                        )
                    ) {
                        unset($items[$key]);
                    }
                }
            }
        }
    }

    /**
     * initializes this class
     *
     * @param int $pageId
     */
    public function init(int $pageId = 0)
    {
        $this->injectLayoutSetup(GeneralUtility::makeInstance(LayoutSetup::class)->init($pageId));
    }

    /**
     * injects layout setup
     *
     * @param LayoutSetup $layoutSetup
     */
    public function injectLayoutSetup(LayoutSetup $layoutSetup)
    {
        $this->layoutSetup = $layoutSetup;
    }
}

