<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */

/**
 * Server-rendered API reference for the Circles Admin app.
 * No external dependencies: inline CSS/JS, styled with Nextcloud's own
 * CSS custom properties so it tracks the user's light/dark theme.
 */

// --- API model. Single source of truth for the rendered reference. ---
$base = '/ocs/v2.php/apps/circlesadmin/api/v1';

$flagRows = [
    ['visible',    '8',     'Visible to everyone',                              'Found in search; otherwise the exact name is required.'],
    ['open',       '16',    'Anyone can join',                                  'Users may join without an invitation.'],
    ['invite',     '32',    'Members must accept the invitation',               'Adding a member creates an invitation to accept.'],
    ['request',    '64',    'Join requests need moderator approval',            'Requests must be confirmed by a moderator.'],
    ['friend',     '128',   'Members can invite others',                        'Members may add new members.'],
    ['protected',  '256',   'Enforce password on shared files',                 'Password on file shares made with the team, not a join password.'],
    ['local',      '4096',  'Local team',                                       'Stays on this instance; the opposite of federated.'],
    ['root',       '8192',  'Cannot be nested',                                 'Team cannot be added as a member of another team. Leave off for teams you want to nest.'],
    ['federated',  '32768', 'Allow federated members',                          'Lets federated users be added via the Contacts app.'],
    ['mountpoint', '65536', 'Generate a Files folder',                          'Creates a Files folder for the team. No UI checkbox.'],
];

$endpoints = [
    [
        'group' => 'Teams',
        'items' => [
            [
                'method' => 'GET', 'path' => '/circles',
                'summary' => 'List all teams',
                'desc' => 'Every team on the instance, including system, hidden and backend teams.',
                'params' => [],
                'body' => null,
                'response' => "[\n  {\n    \"id\": \"abc123\",\n    \"name\": \"My Team\",\n    \"owner\": \"john\",\n    \"memberCount\": 3,\n    \"config\": 0,\n    \"configFlags\": [],\n    \"appManaged\": false,\n    \"federated\": false,\n    \"source\": 16\n  }\n]",
            ],
            [
                'method' => 'GET', 'path' => '/circles/{circleId}',
                'summary' => 'Team details and members',
                'desc' => 'Full team info including description and every member.',
                'params' => [['circleId', 'path', 'Team single ID']],
                'body' => null,
                'response' => "{\n  \"id\": \"abc123\",\n  \"name\": \"My Team\",\n  \"owner\": \"john\",\n  \"config\": 24,\n  \"configFlags\": [\"visible\", \"open\"],\n  \"appManaged\": false,\n  \"federated\": false,\n  \"description\": \"...\",\n  \"members\": [ { \"userId\": \"john\", \"levelName\": \"Owner\", \"statusName\": \"Member\" } ]\n}",
            ],
            [
                'method' => 'POST', 'path' => '/circles',
                'summary' => 'Create a team',
                'desc' => 'Creates a team. Pass config flags to set them at creation. Use appManaged for a locked team.',
                'params' => [
                    ['name', 'body', 'Team name, min 3 characters. Required.'],
                    ['owner', 'body', 'Owner of the team: a user ID, or (with owner_type=circle) a team single ID. Defaults to the admin user. Optional (becomes the role, e.g. moderator, when appManaged).'],
                    ['owner_type', 'body', 'user (default) or circle. circle makes owner a team instead of a user.'],
                    ['desc', 'body', 'Description. Named desc, not description, due to an OCS framework limit.'],
                    ['federated', 'body', 'true for a federated team.'],
                    ['appManaged', 'body', 'true for an app-managed (locked) team.'],
                    ['role', 'body', 'App-managed only: role of owner: moderator (default), admin, member.'],
                    ['members', 'body', 'Optional array to populate the team: each {userId, type: user|circle, level: 1/4/8/9}. Best-effort; failures reported under memberErrors. On an app-managed team, avoid level 9 (Owner) — it transfers ownership away from the app.'],
                    ['<flag>', 'body', 'Any config flag (federated, visible, open, ...) as true/false.'],
                ],
                'body' => "{\n  \"name\": \"New Team\",\n  \"owner\": \"john\",\n  \"desc\": \"Optional\",\n  \"members\": [\n    { \"userId\": \"childTeamId\", \"type\": \"circle\", \"level\": 4 }\n  ]\n}",
                'response' => "{\n  \"id\": \"abc123\",\n  \"name\": \"New Team\",\n  \"owner\": \"john\",\n  \"config\": 36864,\n  \"configFlags\": [\"local\", \"federated\"],\n  \"appManaged\": false,\n  \"federated\": true\n}",
            ],
            [
                'method' => 'PUT', 'path' => '/circles/{circleId}',
                'summary' => 'Update name and description',
                'desc' => 'Provide at least one of name or description.',
                'params' => [
                    ['circleId', 'path', 'Team single ID'],
                    ['name', 'body', 'New name, min 3 characters.'],
                    ['description', 'body', 'New description.'],
                ],
                'body' => "{\n  \"name\": \"Renamed Team\",\n  \"description\": \"...\"\n}",
                'response' => "{\n  \"id\": \"abc123\",\n  \"name\": \"Renamed Team\",\n  \"configFlags\": [],\n  \"appManaged\": false,\n  \"federated\": false\n}",
            ],
            [
                'method' => 'PUT', 'path' => '/circles/{circleId}/config',
                'summary' => 'Set config flags',
                'desc' => 'Toggle one or more flags. Send flag names with true/false. Unmentioned flags stay unchanged.',
                'params' => [
                    ['circleId', 'path', 'Team single ID'],
                    ['<flag>', 'body', 'One or more of the flags below, each true or false.'],
                ],
                'body' => "{\n  \"federated\": true,\n  \"visible\": true,\n  \"local\": false\n}",
                'response' => "{\n  \"id\": \"abc123\",\n  \"config\": 40968,\n  \"configFlags\": [\"visible\", \"federated\"],\n  \"federated\": true\n}",
                'flags' => true,
            ],
            [
                'method' => 'DELETE', 'path' => '/circles/{circleId}',
                'summary' => 'Delete a team',
                'desc' => 'Deletes any team regardless of owner. App-managed teams are unlocked automatically first.',
                'params' => [['circleId', 'path', 'Team single ID']],
                'body' => null,
                'response' => "{\n  \"message\": \"Circle deleted\"\n}",
            ],
        ],
    ],
    [
        'group' => 'Members',
        'items' => [
            [
                'method' => 'GET', 'path' => '/circles/{circleId}/members',
                'summary' => 'List members',
                'desc' => 'Every member of the team.',
                'params' => [['circleId', 'path', 'Team single ID']],
                'body' => null,
                'response' => "[\n  {\n    \"id\": \"mem456\",\n    \"userId\": \"john\",\n    \"level\": 9,\n    \"levelName\": \"Owner\",\n    \"statusName\": \"Member\",\n    \"userType\": 1,\n    \"userTypeName\": \"User\"\n  },\n  {\n    \"id\": \"mem789\",\n    \"userId\": \"Design Team\",\n    \"level\": 4,\n    \"levelName\": \"Moderator\",\n    \"userType\": 16,\n    \"userTypeName\": \"Circle\",\n    \"circle\": { \"id\": \"childTeamId\", \"name\": \"Design Team\" }\n  }\n]",
            ],
            [
                'method' => 'POST', 'path' => '/circles/{circleId}/members',
                'summary' => 'Add a member',
                'desc' => 'Adds a Nextcloud user to the team. Pass type=circle to nest another team as a member.',
                'params' => [
                    ['circleId', 'path', 'Team single ID'],
                    ['userId', 'body', 'User ID, or (for type=circle) the single ID of the team to nest. Required.'],
                    ['type', 'body', 'user (default) or circle. circle adds another team as a member.'],
                ],
                'body' => "{\n  \"userId\": \"jane\",\n  \"type\": \"user\"\n}",
                'response' => "{\n  \"id\": \"mem789\",\n  \"userId\": \"jane\",\n  \"level\": 1,\n  \"levelName\": \"Member\",\n  \"statusName\": \"Member\"\n}",
            ],
            [
                'method' => 'PUT', 'path' => '/circles/{circleId}/members/{memberId}/level',
                'summary' => 'Set member level',
                'desc' => 'Levels: 1 Member, 4 Moderator, 8 Admin, 9 Owner (transfers ownership).',
                'params' => [
                    ['circleId', 'path', 'Team single ID'],
                    ['memberId', 'path', 'Member ID'],
                    ['level', 'body', 'New level: 1, 4, 8 or 9. Required.'],
                ],
                'body' => "{\n  \"level\": 4\n}",
                'response' => "{\n  \"message\": \"Level updated\"\n}",
            ],
            [
                'method' => 'DELETE', 'path' => '/circles/{circleId}/members/{memberId}',
                'summary' => 'Remove a member',
                'desc' => 'Removes a member from the team.',
                'params' => [
                    ['circleId', 'path', 'Team single ID'],
                    ['memberId', 'path', 'Member ID'],
                ],
                'body' => null,
                'response' => "{\n  \"message\": \"Member removed\"\n}",
            ],
        ],
    ],
];

$methodClass = static function (string $m): string {
    return 'cadm-m-' . strtolower($m);
};
?>

<div id="circlesadmin" class="section cadm">
    <h2><?php p($l->t('Circles Admin API')); ?></h2>
    <p class="settings-hint cadm-lede">
        <?php p($l->t('Manage every team on this instance as an administrator, without being a member. Reference for the REST endpoints below.')); ?>
    </p>

    <div class="cadm-meta">
        <div class="cadm-meta-item">
            <span class="cadm-meta-label"><?php p($l->t('Base URL')); ?></span>
            <code class="cadm-code-inline"><?php p($base); ?></code>
        </div>
        <div class="cadm-meta-item">
            <span class="cadm-meta-label"><?php p($l->t('Auth')); ?></span>
            <span class="cadm-meta-value"><?php p($l->t('Admin credentials via Basic Auth')); ?></span>
        </div>
        <div class="cadm-meta-item">
            <span class="cadm-meta-label"><?php p($l->t('Required header')); ?></span>
            <code class="cadm-code-inline">OCS-APIRequest: true</code>
        </div>
    </div>

    <?php foreach ($endpoints as $group): ?>
        <h3 class="cadm-group"><?php p($l->t($group['group'])); ?></h3>
        <ul class="cadm-list">
            <?php foreach ($group['items'] as $i => $ep): ?>
                <li class="cadm-ep">
                    <details<?php if ($group['group'] === 'Teams' && $i === 0) { echo ' open'; } ?>>
                        <summary class="cadm-summary">
                            <span class="cadm-badge <?php p($methodClass($ep['method'])); ?>"><?php p($ep['method']); ?></span>
                            <code class="cadm-path"><?php p($ep['path']); ?></code>
                            <span class="cadm-title"><?php p($l->t($ep['summary'])); ?></span>
                            <span class="cadm-chev" aria-hidden="true">›</span>
                        </summary>
                        <div class="cadm-body">
                            <p class="cadm-desc"><?php p($l->t($ep['desc'])); ?></p>

                            <?php if (!empty($ep['params'])): ?>
                                <div class="cadm-block">
                                    <span class="cadm-block-label"><?php p($l->t('Parameters')); ?></span>
                                    <table class="cadm-params">
                                        <?php foreach ($ep['params'] as $p): ?>
                                            <tr>
                                                <td><code class="cadm-pname"><?php p($p[0]); ?></code></td>
                                                <td><span class="cadm-in cadm-in-<?php p($p[1]); ?>"><?php p($p[1]); ?></span></td>
                                                <td class="cadm-pdesc"><?php p($l->t($p[2])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($ep['flags'])): ?>
                                <div class="cadm-block">
                                    <span class="cadm-block-label"><?php p($l->t('Config flags')); ?></span>
                                    <table class="cadm-params cadm-flags">
                                        <?php foreach ($flagRows as $f): ?>
                                            <tr>
                                                <td><code class="cadm-pname"><?php p($f[0]); ?></code></td>
                                                <td><span class="cadm-bit"><?php p($f[1]); ?></span></td>
                                                <td class="cadm-pdesc"><strong><?php p($l->t($f[2])); ?></strong> &mdash; <?php p($l->t($f[3])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <div class="cadm-io">
                                <?php if (!empty($ep['body'])): ?>
                                    <div class="cadm-block cadm-io-col">
                                        <span class="cadm-block-label"><?php p($l->t('Request body')); ?></span>
                                        <pre class="cadm-pre"><code><?php p($ep['body']); ?></code></pre>
                                    </div>
                                <?php endif; ?>
                                <div class="cadm-block cadm-io-col">
                                    <span class="cadm-block-label"><?php p($l->t('Example response')); ?></span>
                                    <pre class="cadm-pre"><code><?php p($ep['response']); ?></code></pre>
                                </div>
                            </div>
                        </div>
                    </details>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>

    <details class="cadm-example">
        <summary class="cadm-summary cadm-summary-plain">
            <span class="cadm-title"><?php p($l->t('Example: create a federated team with curl')); ?></span>
            <span class="cadm-chev" aria-hidden="true">›</span>
        </summary>
        <div class="cadm-body">
            <pre class="cadm-pre"><code>curl -u admin:password \
  -H "OCS-APIRequest: true" \
  -H "Accept: application/json" \
  -X POST "<?php p($base); ?>/circles" \
  -d '{"name":"Federated Team","owner":"alice","federated":true}'</code></pre>
        </div>
    </details>
</div>

<style>
/* Scoped to #circlesadmin. Uses Nextcloud theme variables so it tracks light/dark. */
#circlesadmin.cadm {
    max-width: 900px;
    --cadm-radius: 8px;
    --cadm-line: var(--color-border, #ddd);
    --cadm-mono: var(--font-face-monospace, ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace);
}
#circlesadmin .cadm-lede { max-width: 68ch; margin-bottom: 20px; }

#circlesadmin .cadm-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 28px;
    padding: 14px 16px;
    margin-bottom: 28px;
    background: var(--color-background-hover, #f5f5f5);
    border-radius: var(--cadm-radius);
}
#circlesadmin .cadm-meta-item { display: flex; flex-direction: column; gap: 3px; }
#circlesadmin .cadm-meta-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-text-maxcontrast, #767676);
}
#circlesadmin .cadm-meta-value { font-size: 13px; }

#circlesadmin .cadm-group {
    margin: 30px 0 8px;
    padding-bottom: 6px;
    font-size: 15px;
    font-weight: 700;
    border-bottom: 1px solid var(--cadm-line);
}
#circlesadmin .cadm-group:first-of-type { margin-top: 8px; }

#circlesadmin .cadm-list { list-style: none; padding: 0; margin: 0; }
#circlesadmin .cadm-ep {
    border: 1px solid var(--cadm-line);
    border-radius: var(--cadm-radius);
    margin: 8px 0;
    overflow: hidden;
    background: var(--color-main-background, #fff);
    transition: border-color 220ms cubic-bezier(0.22, 1, 0.36, 1);
}
#circlesadmin .cadm-ep:has(details[open]) { border-color: var(--color-primary-element, #0082c9); }

#circlesadmin .cadm-summary {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    cursor: pointer;
    list-style: none;
    user-select: none;
}
#circlesadmin .cadm-summary::-webkit-details-marker { display: none; }
#circlesadmin .cadm-summary:hover { background: var(--color-background-hover, #f5f5f5); }
#circlesadmin .cadm-summary:focus-visible {
    outline: 2px solid var(--color-primary-element, #0082c9);
    outline-offset: -2px;
}

#circlesadmin .cadm-badge {
    flex: 0 0 auto;
    min-width: 58px;
    text-align: center;
    padding: 3px 8px;
    border-radius: 5px;
    font-family: var(--cadm-mono);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: #fff;
}
/* Method colors: the one place color carries meaning. Tinted, not garish. */
#circlesadmin .cadm-m-get    { background: oklch(0.58 0.11 236); }
#circlesadmin .cadm-m-post   { background: oklch(0.60 0.13 152); }
#circlesadmin .cadm-m-put    { background: oklch(0.66 0.13 66);  }
#circlesadmin .cadm-m-delete { background: oklch(0.58 0.16 24);  }

#circlesadmin .cadm-path {
    flex: 0 1 auto;
    font-family: var(--cadm-mono);
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#circlesadmin .cadm-title {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 13px;
    color: var(--color-text-maxcontrast, #767676);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
#circlesadmin .cadm-chev {
    flex: 0 0 auto;
    font-size: 20px;
    line-height: 1;
    color: var(--color-text-maxcontrast, #767676);
    transition: transform 240ms cubic-bezier(0.22, 1, 0.36, 1);
}
#circlesadmin details[open] > .cadm-summary .cadm-chev { transform: rotate(90deg); }

#circlesadmin .cadm-body {
    padding: 4px 16px 18px;
    border-top: 1px solid var(--cadm-line);
}
#circlesadmin .cadm-desc {
    margin: 12px 0 16px;
    max-width: 70ch;
    color: var(--color-main-text, #222);
}

#circlesadmin .cadm-block { margin-bottom: 16px; }
#circlesadmin .cadm-block-label {
    display: block;
    margin-bottom: 6px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-text-maxcontrast, #767676);
}

#circlesadmin .cadm-params { border-collapse: collapse; width: 100%; }
#circlesadmin .cadm-params td {
    padding: 5px 12px 5px 0;
    vertical-align: top;
    border-bottom: 1px solid var(--color-border-dark, #ededed);
    font-size: 13px;
}
#circlesadmin .cadm-params tr:last-child td { border-bottom: none; }
#circlesadmin .cadm-pname { font-family: var(--cadm-mono); font-weight: 600; white-space: nowrap; }
#circlesadmin .cadm-pdesc { color: var(--color-main-text, #222); width: 100%; }
#circlesadmin .cadm-bit {
    font-family: var(--cadm-mono);
    font-size: 12px;
    color: var(--color-text-maxcontrast, #767676);
}

#circlesadmin .cadm-in {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    background: var(--color-background-dark, #ededed);
    color: var(--color-text-maxcontrast, #767676);
}
#circlesadmin .cadm-in-body { background: color-mix(in oklch, var(--color-primary-element, #0082c9) 16%, transparent); color: var(--color-primary-element-text-dark, var(--color-primary-element, #0082c9)); }

#circlesadmin .cadm-io { display: flex; flex-wrap: wrap; gap: 16px; }
#circlesadmin .cadm-io-col { flex: 1 1 260px; min-width: 0; margin-bottom: 0; }

#circlesadmin .cadm-pre {
    margin: 0;
    padding: 12px 14px;
    background: var(--color-background-dark, #f5f5f5);
    border-radius: 6px;
    overflow-x: auto;
    font-family: var(--cadm-mono);
    font-size: 12.5px;
    line-height: 1.55;
}
#circlesadmin .cadm-pre code { font-family: inherit; white-space: pre; }
#circlesadmin .cadm-code-inline {
    font-family: var(--cadm-mono);
    font-size: 13px;
    padding: 1px 6px;
    background: var(--color-background-dark, #ededed);
    border-radius: 4px;
}

#circlesadmin .cadm-example { margin-top: 28px; }
#circlesadmin .cadm-summary-plain {
    border: 1px dashed var(--cadm-line);
    border-radius: var(--cadm-radius);
}
#circlesadmin .cadm-summary-plain:hover { background: var(--color-background-hover, #f5f5f5); }
#circlesadmin .cadm-example[open] .cadm-summary-plain { border-style: solid; border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
#circlesadmin .cadm-example .cadm-body { border: 1px solid var(--cadm-line); border-top: none; border-radius: 0 0 var(--cadm-radius) var(--cadm-radius); }

@media (max-width: 560px) {
    #circlesadmin .cadm-title { display: none; }
    #circlesadmin .cadm-io { flex-direction: column; }
}
</style>
