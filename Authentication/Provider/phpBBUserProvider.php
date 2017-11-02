<?php
/**
 *
 * @package phpBBSessionsAuthBundle
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license MIT
 *
 */

namespace phpBB\SessionsAuthBundle\Authentication\Provider;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class phpBBUserProvider implements UserProviderInterface
{
    const ANONYMOUS_USER_ID = 1;

    /**
     * entityManager
     *
     * @var EntityManager
     * @access private
     */
    private $entityManager;

    /**
     * __construct
     *
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param $apiKey
     */
    public function getUsernameForSessionId($sessionId, $expectedUserId, $userIp)
    {
        if (!$sessionId) {
            return null;
        }

        $session = $this->entityManager
            ->getRepository('phpbbSessionsAuthBundle:Session')
            ->findOneById($sessionId, [ 'time' => 'DESC' ])
        ;

        if (!$session) {
            return null;
        }

        $user = $session->getUser();

        if (!$user || $user->getId() === self::ANONYMOUS_USER_ID) {
            return null;
        }

        if ($user->getId() != $expectedUserId) {
            return null;
        }


        if (strpos($userIp, ':') !== false && strpos($session->getIp(), ':') !== false)
        {
            $s_ip = $this->shortIpv6($session->getIp(), 3);
            $u_ip = $this->shortIpv6($userIp, 3);
        }
        else
        {
            $s_ip = implode('.', array_slice(explode('.', $session->getIp()), 0, 3));
            $u_ip = implode('.', array_slice(explode('.', $userIp), 0, 3));
        }

        // Assume session length of 3600
        $isIpOk = $u_ip === $s_ip;
        $isSessionTimeOk = $session->getTime() + 3660 >= time();
        $isAutologin = $session->getAutologin();
        if ($isIpOk && ($isAutologin || $isSessionTimeOk))
        {
            return $user->getUsername();
        }

        return null;
    }

    /**
     * @param string $username
     * @return UserInterface|void
     */
    public function loadUserByUsername($username)
    {
        $user = $this->entityManager
            ->getRepository('phpbbSessionsAuthBundle:User')
            ->findOneByUsername($username);

        return $user;
    }

    /**
     * @param UserInterface $user
     * @return UserInterface|void
     */
    public function refreshUser(UserInterface $user)
    {
        return $user;
    }

    /**
     * @param string $class
     * @return bool
     */
    public function supportsClass($class)
    {
        return 'phpBB\SessionsAuthBundle\Entity\User' === $class;
    }

    /**
     * Returns the first block of the specified IPv6 address and as many additional
     * ones as specified in the length paramater.
     * If length is zero, then an empty string is returned.
     * If length is greater than 3 the complete IP will be returned
     *
     * @copyright (c) phpBB Limited <https://www.phpbb.com>
     * @license GNU General Public License, version 2 (GPL-2.0)
     *
     * @param $ip
     * @param $length
     * @return mixed|string
     */
    private function shortIpv6($ip, $length)
    {
        if ($length < 1)
        {
            return '';
        }

        // extend IPv6 addresses
        $blocks = substr_count($ip, ':') + 1;
        if ($blocks < 9)
        {
            $ip = str_replace('::', ':' . str_repeat('0000:', 9 - $blocks), $ip);
        }
        if ($ip[0] == ':')
        {
            $ip = '0000' . $ip;
        }
        if ($length < 4)
        {
            $ip = implode(':', array_slice(explode(':', $ip), 0, 1 + $length));
        }

        return $ip;
    }
}
