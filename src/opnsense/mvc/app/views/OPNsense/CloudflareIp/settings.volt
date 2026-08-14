<div class="content">
    <ul class="nav nav-tabs" data-tabs="tabs">
        <li class="active"><a data-toggle="tab" href="#general">General</a></li>
        <li><a data-toggle="tab" href="#wans">WAN interfaces</a></li>
    </ul>
    <div class="tab-content content-box tab-content">
        <div id="general" class="tab-pane fade in active">
            <form id="frm_general" method="post">
                <table class="table table-condensed">
                    <tr>
                        <td><label for="general.enabled">Enabled</label></td>
                        <td>{{ partial 'layout_partials/base_form_field' fields=generalForm.enabled }}</td>
                    </tr>
                    <tr>
                        <td><label for="general.account_id">Cloudflare Account ID</label></td>
                        <td>{{ partial 'layout_partials/base_form_field' fields=generalForm.account_id }}</td>
                    </tr>
                    <tr>
                        <td><label for="general.api_token">API token</label></td>
                        <td>{{ partial 'layout_partials/base_form_field' fields=generalForm.api_token }}</td>
                    </tr>
                    <tr>
                        <td><label for="general.interval">Check interval (minutes)</label></td>
                        <td>{{ partial 'layout_partials/base_form_field' fields=generalForm.interval }}</td>
                    </tr>
                </table>
                <hr />
                <button id="saveGeneralBtn" type="button" class="btn btn-primary">Save</button>
            </form>
        </div>
        <div id="wans" class="tab-pane fade">
            <table id="grid-wans" class="table table-condensed table-hover table-striped" data-table="wans">
                <thead>
                    <tr>
                        <th data-column-id="enabled" data-width="60px" data-formatter="enabled">Enabled</th>
                        <th data-column-id="interface" data-identifier="true">Interface</th>
                        <th data-column-id="list_name">Cloudflare list name</th>
                        <th data-column-id="include_v6">Include v6</th>
                        <th data-column-id="description">Description</th>
                        <th data-column-id="commands" data-formatter="commands" data-sortable="false">Commands</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <button id="addWanBtn" type="button" class="btn btn-default">Add</button>
        </div>
    </div>
    <div class="col-md-12">
        <button id="updateNowBtn" type="button" class="btn btn-primary">Update now</button>
        <button id="refreshStatusBtn" type="button" class="btn btn-default">Refresh status</button>
        <pre id="statusPanel" class="pre-scrollable" style="margin-top:10px; max-height:300px;"></pre>
    </div>
</div>

<!-- WAN edit dialog -->
<div id="wanDialog" class="modal fade">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3>Edit WAN</h3>
            </div>
            <div class="modal-body">
                <form class="form-horizontal" id="frm_wan">
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="wan.interface">Interface</label>
                        <div class="col-sm-7">
                            <input type="text" id="wan.interface" name="wan.interface" class="form-control" placeholder="wan" />
                            <p class="help-block">OPNsense interface name (e.g. wan, opt1)</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="wan.list_name">List name</label>
                        <div class="col-sm-7">
                            <input type="text" id="wan.list_name" name="wan.list_name" class="form-control" placeholder="home-egress-wan" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="wan.include_v6">Include IPv6</label>
                        <div class="col-sm-7">
                            <input type="checkbox" id="wan.include_v6" name="wan.include_v6" value="1" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="wan.enabled">Enabled</label>
                        <div class="col-sm-7">
                            <input type="checkbox" id="wan.enabled" name="wan.enabled" value="1" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="wan.description">Description</label>
                        <div class="col-sm-7">
                            <input type="text" id="wan.description" name="wan.description" class="form-control" />
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="saveWanBtn" type="button" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>
