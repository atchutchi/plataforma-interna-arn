<?php
/**
 * Operações conhecidas pelo motor puro.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

/** Uma operação desconhecida não pode obter uma concessão. */
enum Action {
	case ReadContent;
	case ReadDirectory;
	case UpdateOwnProfile;
	case ReadOwnTask;
	case UpdateOwnTask;
	case AssignUnitTask;
	case ReadUnitTask;
	case PublishTeamNotice;
	case ReadHrRecord;
	case WriteHrRecord;
	case CreateNewsDraft;
	case SubmitNews;
	case ReviewNews;
	case ApproveNews;
	case PublishNews;
	case ManageAccount;
	case ManageGrants;
}
