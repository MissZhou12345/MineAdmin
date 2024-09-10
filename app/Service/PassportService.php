<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */

namespace App\Service;

use App\Events\User\LoginSuccessEvent;
use App\Exception\BusinessException;
use App\Exception\JwtInBlackException;
use App\Http\Common\ResultCode;
use App\Repository\Permission\UserRepository;
use Hyperf\Collection\Arr;
use Lcobucci\JWT\UnencryptedToken;
use Mine\Kernel\Jwt\Factory;
use Mine\Kernel\Jwt\JwtInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final class PassportService extends IService
{
    /**
     * @var string jwt场景
     */
    private string $jwt = 'default';

    public function __construct(
        protected readonly UserRepository $repository,
        protected readonly Factory $jwtFactory,
        protected readonly EventDispatcherInterface $dispatcher
    ) {}

    /**
     * @return array<string,int|string>
     */
    public function login(string $username, string $password, int $userType = 100): array
    {
        $user = $this->repository->findByUnameType($username, $userType);
        if (! $user->verifyPassword($password)) {
            throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, trans('auth.password_error'));
        }
        $this->dispatcher->dispatch(new LoginSuccessEvent($user));
        $jwt = $this->getJwt();
        $token = $jwt->builder($user->only(['id']));
        return [
            'token' => $token->toString(),
            'expire_at' => (int) $jwt->getConfig('ttl', 0),
        ];
    }

    public function checkJwt(UnencryptedToken $token): bool
    {
        return value(function (JwtInterface $jwt) use ($token) {
            if ($jwt->hasBlackList($token)) {
                throw new JwtInBlackException();
            }
            return true;
        }, $this->getJwt());
    }

    public function logout(UnencryptedToken $token): void
    {
        $this->getJwt()->addBlackList($token);
    }

    public function getJwt(): JwtInterface
    {
        return $this->jwtFactory->get($this->jwt);
    }

    /**
     * @return array<string,int|string>
     */
    public function refreshToken(UnencryptedToken $token): array
    {
        return value(function (JwtInterface $jwt) use ($token) {
            $jwt->addBlackList($token);
            return [
                'token' => $jwt->builder(Arr::only($token->claims()->all(), 'id'))->toString(),
                'expire_at' => (int) $jwt->getConfig('ttl', 0),
            ];
        }, $this->getJwt());
    }
}
