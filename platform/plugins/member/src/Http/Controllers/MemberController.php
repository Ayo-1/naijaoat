<?php

namespace Botble\Member\Http\Controllers;

use Botble\Base\Events\BeforeEditContentEvent;
use Botble\Base\Events\CreatedContentEvent;
use Botble\Base\Events\DeletedContentEvent;
use Botble\Base\Events\UpdatedContentEvent;
use Botble\Base\Forms\FormBuilder;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Base\Traits\HasDeleteManyItemsTrait;
use Botble\Media\Repositories\Interfaces\MediaFileInterface;
use Botble\Member\Forms\MemberForm;
use Botble\Member\Http\Requests\MemberCreateRequest;
use Botble\Member\Http\Requests\MemberEditRequest;
use Botble\Member\Repositories\Interfaces\MemberInterface;
use Botble\Member\Tables\MemberTable;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class MemberController extends BaseController
{
    use HasDeleteManyItemsTrait;

    /**
     * @var MemberInterface
     */
    protected $memberRepository;

    /**
     * @param MemberInterface $memberRepository
     */
    public function __construct(MemberInterface $memberRepository)
    {
        $this->memberRepository = $memberRepository;
    }

    /**
     * @param MemberTable $dataTable
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Throwable
     */
    public function index(MemberTable $dataTable)
    {
        page_title()->setTitle(trans('plugins/member::member.menu_name'));

        return $dataTable->renderTable();
    }

    /**
     * @param FormBuilder $formBuilder
     * @return string
     */
    public function create(FormBuilder $formBuilder)
    {
        page_title()->setTitle(trans('plugins/member::member.create'));

        return $formBuilder
            ->create(MemberForm::class)
            ->remove('is_change_password')
            ->renderForm();
    }

    /**
     * @param MemberCreateRequest $request
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse
     */
    public function store(MemberCreateRequest $request, BaseHttpResponse $response)
    {
        $member = $this->memberRepository->getModel();
        $member->fill($request->input());
        $member->confirmed_at = now();
        $member->password = bcrypt($request->input('password'));
        $member->dob = Carbon::parse($request->input('dob'))->toDateString();

        if ($request->input('avatar_image')) {
            $image = app(MediaFileInterface::class)->getFirstBy(['url' => $request->input('avatar_image')]);
            if ($image) {
                $member->avatar_id = $image->id;
            }
        }

        $member = $this->memberRepository->createOrUpdate($member);

        event(new CreatedContentEvent(MEMBER_MODULE_SCREEN_NAME, $request, $member));

        return $response
            ->setPreviousUrl(route('member.index'))
            ->setNextUrl(route('member.edit', $member->id))
            ->setMessage(trans('core/base::notices.create_success_message'));
    }

    /**
     * @param int $id
     * @param FormBuilder $formBuilder
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|string
     */
    public function edit($id, FormBuilder $formBuilder, Request $request)
    {
        $member = $this->memberRepository->findOrFail($id);

        event(new BeforeEditContentEvent($request, $member));

        page_title()->setTitle(trans('plugins/member::member.edit'));

        $member->password = null;

        return $formBuilder
            ->create(MemberForm::class, ['model' => $member])
            ->renderForm();
    }

    /**
     * @param $id
     * @param MemberEditRequest $request
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse
     */
    public function update($id, MemberEditRequest $request, BaseHttpResponse $response)
    {
        $member = $this->memberRepository->findOrFail($id);

        $member->fill($request->except('password'));

        if ($request->input('is_change_password') == 1) {
            $member->password = bcrypt($request->input('password'));
        }

        $member->dob = Carbon::parse($request->input('dob'))->toDateString();

        if ($request->input('avatar_image')) {
            $image = app(MediaFileInterface::class)->getFirstBy(['url' => $request->input('avatar_image')]);
            if ($image) {
                $member->avatar_id = $image->id;
            }
        }

        $member = $this->memberRepository->createOrUpdate($member);

        event(new UpdatedContentEvent(MEMBER_MODULE_SCREEN_NAME, $request, $member));

        return $response
            ->setPreviousUrl(route('member.index'))
            ->setMessage(trans('core/base::notices.update_success_message'));
    }

    /**
     * @param Request $request
     * @param $id
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse
     */
    public function destroy(Request $request, $id, BaseHttpResponse $response)
    {
        try {
            $member = $this->memberRepository->findOrFail($id);
            $this->memberRepository->delete($member);
            event(new DeletedContentEvent(MEMBER_MODULE_SCREEN_NAME, $request, $member));

            return $response->setMessage(trans('core/base::notices.delete_success_message'));
        } catch (Exception $exception) {
            return $response
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    /**
     * @param Request $request
     * @param BaseHttpResponse $response
     * @return BaseHttpResponse
     * @throws Exception
     */
    public function deletes(Request $request, BaseHttpResponse $response)
    {
        return $this->executeDeleteItems($request, $response, $this->memberRepository, MEMBER_MODULE_SCREEN_NAME);
    }
}
