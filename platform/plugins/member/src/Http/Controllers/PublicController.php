<?php

namespace Botble\Member\Http\Controllers;

use Assets;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Media\Chunks\Exceptions\UploadMissingFileException;
use Botble\Media\Chunks\Handler\DropZoneUploadHandler;
use Botble\Media\Chunks\Receiver\FileReceiver;
use Botble\Media\Repositories\Interfaces\MediaFileInterface;
use Botble\Media\Services\ThumbnailService;
use Botble\Member\Http\Requests\AvatarRequest;
use Botble\Member\Http\Requests\SettingRequest;
use Botble\Member\Http\Requests\UpdatePasswordRequest;
use Botble\Member\Http\Resources\ActivityLogResource;
use Botble\Member\Repositories\Interfaces\MemberActivityLogInterface;
use Botble\Member\Repositories\Interfaces\MemberInterface;
use Exception;
use File;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use RvMedia;
use SeoHelper;

class PublicController extends Controller
{
    /**
     * @var MemberInterface
     */
    protected $memberRepository;

    /**
     * @var MemberActivityLogInterface
     */
    protected $activityLogRepository;

    /**
     * @var MediaFileInterface
     */
    protected $fileRepository;

    /**
     * PublicController constructor.
     * @param Repository $config
     * @param MemberInterface $memberRepository
     * @param MemberActivityLogInterface $memberActivityLogRepository
     * @param MediaFileInterface $fileRepository
     */
    public function __construct(
        Repository $config,
        MemberInterface $memberRepository,
        MemberActivityLogInterface $memberActivityLogRepository,
        MediaFileInterface $fileRepository
    ) {
        $this->memberRepository = $memberRepository;
        $this->activityLogRepository = $memberActivityLogRepository;
        $this->fileRepository = $fileRepository;

        Assets::setConfig($config->get('plugins.member.assets', []));
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function getDashboard()
    {
        $user = auth('member')->user();

        SeoHelper::setTitle($user->name);

        return view('plugins/member::dashboard.index', compact('user'));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getSettings()
    {
        SeoHelper::setTitle(__('Account settings'));

        $user = auth('member')->user();

        return view('plugins/member::settings.index', compact('user'));
    }

    /**
     * @param SettingRequest $request
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse|\Illuminate\Http\RedirectResponse
     */
    public function postSettings(SettingRequest $request, BaseHttpResponse $response)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $day = $request->input('day');

        if ($year && $month && $day) {
            $request->merge(['dob' => implode('-', [$year, $month, $day])]);

            $validator = Validator::make($request->input(), [
                'dob' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return redirect()->route('public.member.settings');
            }
        }

        $this->memberRepository->createOrUpdate($request->except('email'), ['id' => auth('member')->id()]);

        $this->activityLogRepository->createOrUpdate(['action' => 'update_setting']);

        return $response
            ->setNextUrl(route('public.member.settings'))
            ->setMessage(__('Update profile successfully!'));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getSecurity()
    {
        SeoHelper::setTitle(__('Security'));

        return view('plugins/member::settings.security');
    }

    /**
     * @param UpdatePasswordRequest $request
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse
     */
    public function postSecurity(UpdatePasswordRequest $request, BaseHttpResponse $response)
    {
        $this->memberRepository->update(['id' => auth('member')->id()], [
            'password' => bcrypt($request->input('password')),
        ]);

        $this->activityLogRepository->createOrUpdate(['action' => 'update_security']);

        return $response->setMessage(trans('plugins/member::dashboard.password_update_success'));
    }

    /**
     * @param AvatarRequest $request
     * @param ThumbnailService $thumbnailService
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse
     */
    public function postAvatar(AvatarRequest $request, ThumbnailService $thumbnailService, BaseHttpResponse $response)
    {
        try {
            $account = auth('member')->user();

            $result = RvMedia::handleUpload($request->file('avatar_file'), 0, 'members');

            if ($result['error'] != false) {
                return $response->setError()->setMessage($result['message']);
            }

            $avatarData = json_decode($request->input('avatar_data'));

            $file = $result['data'];

            $thumbnailService
                ->setImage(RvMedia::getRealPath($file->url))
                ->setSize((int)$avatarData->width, (int)$avatarData->height)
                ->setCoordinates((int)$avatarData->x, (int)$avatarData->y)
                ->setDestinationPath(File::dirname($file->url))
                ->setFileName(File::name($file->url) . '.' . File::extension($file->url))
                ->save('crop');

            $this->fileRepository->forceDelete(['id' => $account->avatar_id]);

            $account->avatar_id = $file->id;

            $this->memberRepository->createOrUpdate($account);

            $this->activityLogRepository->createOrUpdate([
                'action' => 'changed_avatar',
            ]);

            return $response
                ->setMessage(trans('plugins/member::dashboard.update_avatar_success'))
                ->setData(['url' => RvMedia::url($file->url)]);
        } catch (Exception $exception) {
            return $response
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    /**
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse
     */
    public function getActivityLogs(BaseHttpResponse $response)
    {
        $activities = $this->activityLogRepository->getAllLogs(auth('member')->id());

        return $response->setData(ActivityLogResource::collection($activities))->toApiResponse();
    }

    /**
     * @param Request $request
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse|\Illuminate\Http\JsonResponse
     */
    public function postUpload(Request $request, BaseHttpResponse $response)
    {
        if (!RvMedia::isChunkUploadEnabled()) {
            $validator = Validator::make($request->all(), [
                'file.0' => RvMedia::imageValidationRule(),
            ]);

            if ($validator->fails()) {
                return $response->setError()->setMessage($validator->getMessageBag()->first());
            }

            $result = RvMedia::handleUpload(Arr::first($request->file('file')), 0, 'accounts');

            if ($result['error']) {
                return $response->setError()->setMessage($result['message']);
            }

            return $response->setData($result['data']);
        }

        try {
            // Create the file receiver
            $receiver = new FileReceiver('file', $request, DropZoneUploadHandler::class);
            // Check if the upload is success, throw exception or return response you need
            if ($receiver->isUploaded() === false) {
                throw new UploadMissingFileException;
            }
            // Receive the file
            $save = $receiver->receive();
            // Check if the upload has finished (in chunk mode it will send smaller files)
            if ($save->isFinished()) {
                $result = RvMedia::handleUpload($save->getFile(), 0, 'accounts');

                if ($result['error'] == false) {
                    return $response->setData($result['data']);
                }

                return $response->setError()->setMessage($result['message']);
            }
            // We are in chunk mode, lets send the current progress
            $handler = $save->handler();
            return response()->json([
                'done'   => $handler->getPercentageDone(),
                'status' => true,
            ]);
        } catch (Exception $exception) {
            return $response->setError()->setMessage($exception->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function postUploadFromEditor(Request $request)
    {
        return RvMedia::uploadFromEditor($request, 0, 'accounts');
    }
}
