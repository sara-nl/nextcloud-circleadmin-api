<?php
/** @var array $_ */
?>
<div id="circlesadmin" class="section">
    <h2><?php p($l->t('Circles Admin')); ?></h2>
    <p><?php p($l->t('Use the API endpoints to manage all circles as admin.')); ?></p>

    <h3><?php p($l->t('API Endpoints')); ?></h3>
    <table class="grid">
        <thead>
            <tr>
                <th>Method</th>
                <th>Endpoint</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>GET</td><td>.../api/v1/circles</td><td>List all circles</td></tr>
            <tr><td>GET</td><td>.../api/v1/circles/{circleId}</td><td>Circle details + members</td></tr>
            <tr><td>POST</td><td>.../api/v1/circles</td><td>Create circle (name, owner, desc, local)</td></tr>
            <tr><td>PUT</td><td>.../api/v1/circles/{circleId}</td><td>Update circle (name, description)</td></tr>
            <tr><td>DELETE</td><td>.../api/v1/circles/{circleId}</td><td>Delete circle</td></tr>
            <tr><td>GET</td><td>.../api/v1/circles/{circleId}/members</td><td>List members</td></tr>
            <tr><td>POST</td><td>.../api/v1/circles/{circleId}/members</td><td>Add member (userId)</td></tr>
            <tr><td>DELETE</td><td>.../api/v1/circles/{circleId}/members/{memberId}</td><td>Remove member</td></tr>
            <tr><td>PUT</td><td>.../api/v1/circles/{circleId}/members/{memberId}/level</td><td>Set level (1/4/8/9)</td></tr>
        </tbody>
    </table>

    <p style="margin-top:10px">
        <?php p($l->t('Base URL: /ocs/v2.php/apps/circlesadmin')); ?><br/>
        <?php p($l->t('All endpoints require admin credentials via Basic Auth + OCS-APIRequest header.')); ?>
    </p>
</div>
