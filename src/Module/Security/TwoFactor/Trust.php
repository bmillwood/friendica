<?php

// Copyright (C) 2010-2024, the Friendica project
// SPDX-FileCopyrightText: 2010-2024 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Friendica\Module\Security\TwoFactor;

use Friendica\App\Arguments;
use Friendica\App\BaseURL;
use Friendica\AppHelper;
use Friendica\BaseModule;
use Friendica\Core\L10n;
use Friendica\Core\Renderer;
use Friendica\Core\Session\Capability\IHandleUserSessions;
use Friendica\DI;
use Friendica\Model\User;
use Friendica\Model\User\Cookie;
use Friendica\Module\Response;
use Friendica\Network\HTTPException\FoundException;
use Friendica\Network\HTTPException\MovedPermanentlyException;
use Friendica\Network\HTTPException\TemporaryRedirectException;
use Friendica\Security\Authentication;
use Friendica\Security\TwoFactor\Exception\TrustedBrowserNotFoundException;
use Friendica\Security\TwoFactor\Exception\TrustedBrowserPersistenceException;
use Friendica\Util\Profiler;
use Friendica\Security\TwoFactor;
use Psr\Log\LoggerInterface;

/**
 * Page 2: Trust Browser dialog
 *
 * @package Friendica\Module\TwoFactor
 */
class Trust extends BaseModule
{
	/** @var AppHelper */
	protected $appHelper;
	/** @var Authentication  */
	protected $auth;
	/** @var IHandleUserSessions  */
	protected $session;
	/** @var Cookie  */
	protected $cookie;
	/** @var TwoFactor\Factory\TrustedBrowser  */
	protected $trustedBrowserFactory;
	/** @var TwoFactor\Repository\TrustedBrowser  */
	protected $trustedBrowserRepository;

	public function __construct(AppHelper $appHelper, Authentication $auth, L10n $l10n, BaseURL $baseUrl, Arguments $args, LoggerInterface $logger, Profiler $profiler, IHandleUserSessions $session, Cookie $cookie, TwoFactor\Factory\TrustedBrowser $trustedBrowserFactory, TwoFactor\Repository\TrustedBrowser $trustedBrowserRepository, Response $response, array $server, array $parameters = [])
	{
		parent::__construct($l10n, $baseUrl, $args, $logger, $profiler, $response, $server, $parameters);

		$this->appHelper                = $appHelper;
		$this->auth                     = $auth;
		$this->session                  = $session;
		$this->cookie                   = $cookie;
		$this->trustedBrowserFactory    = $trustedBrowserFactory;
		$this->trustedBrowserRepository = $trustedBrowserRepository;
	}

	protected function post(array $request = [])
	{
		if (!$this->session->getLocalUserId() || !$this->session->get('2fa')) {
			$this->logger->info('Invalid call', ['request' => $request]);
			return;
		}

		$action = $request['action'] ?? '';

		if (!empty($action)) {
			self::checkFormSecurityTokenRedirectOnError('2fa', 'twofactor_trust');

			switch ($action) {
				case 'trust':
				case 'dont_trust':
					$trustedBrowser = $this->trustedBrowserFactory->createForUserWithUserAgent($this->session->getLocalUserId(), $this->server['HTTP_USER_AGENT'], $action === 'trust');
					try {
						$this->trustedBrowserRepository->save($trustedBrowser);

						// The string is sent to the browser to be sent back with each request
						if (!$this->cookie->set('2fa_cookie_hash', $trustedBrowser->cookie_hash)) {
							DI::sysmsg()->addNotice($this->t('Couldn\'t save browser to Cookie.'));
						};
					} catch (TrustedBrowserPersistenceException $exception) {
						$this->logger->warning('Unexpected error when saving the trusted browser.', ['trustedBrowser' => $trustedBrowser, 'exception' => $exception]);
					}
					break;
			}

			try {
				$this->auth->setForUser(User::getById($this->session->getLocalUserId()), true, true);
				$this->baseUrl->redirect($this->session->pop('return_path', ''));
			} catch (FoundException | TemporaryRedirectException | MovedPermanentlyException $e) {
				// exception wanted!
				throw $e;
			} catch (\Exception $e) {
				$this->logger->warning('Unexpected error during authentication.', ['user' => $this->session->getLocalUserId(), 'exception' => $e]);
			}
		}
	}

	protected function content(array $request = []): string
	{
		if (!$this->session->getLocalUserId() || !$this->session->get('2fa')) {
			$this->baseUrl->redirect();
		}

		if ($this->cookie->get('2fa_cookie_hash')) {
			try {
				$trustedBrowser = $this->trustedBrowserRepository->selectOneByHash($this->cookie->get('2fa_cookie_hash'));
				if (!$trustedBrowser->trusted) {
					$this->auth->setForUser(User::getById($this->session->getLocalUserId()), true, true);
					$this->baseUrl->redirect($this->session->pop('return_path', ''));
				}
			} catch (TrustedBrowserNotFoundException $exception) {
				$this->logger->notice('Trusted Browser of the cookie not found.', ['cookie_hash' => $this->cookie->get('trusted'), 'uid' => $this->session->getLocalUserId(), 'exception' => $exception]);
			} catch (TrustedBrowserPersistenceException $exception) {
				$this->logger->warning('Unexpected persistence exception.', ['cookie_hash' => $this->cookie->get('trusted'), 'uid' => $this->session->getLocalUserId(), 'exception' => $exception]);
			} catch (\Exception $exception) {
				$this->logger->warning('Unexpected exception.', ['cookie_hash' => $this->cookie->get('trusted'), 'uid' => $this->session->getLocalUserId(), 'exception' => $exception]);
			}
		}

		return Renderer::replaceMacros(Renderer::getMarkupTemplate('twofactor/trust.tpl'), [
			'$form_security_token' => self::getFormSecurityToken('twofactor_trust'),

			'$title'            => $this->t('Trust this browser?'),
			'$message'          => $this->t('<p>If you choose to trust this browser, you will not be asked for a verification code the next time you sign in.</p>'),
			'$not_now_label'    => $this->t('Not now'),
			'$dont_trust_label' => $this->t('Don\'t trust'),
			'$trust_label'      => $this->t('Trust'),
		]);
	}
}
