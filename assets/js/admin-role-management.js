(function () {
    if (!window.AdminRoleConfig || !window.jQuery) {
        return;
    }

    var cfg = window.AdminRoleConfig;
    var $ = window.jQuery;
    var state = {
        admins: [],
        selected: new Set(),
        editingAdmin: null,
        members: [],
        editingMember: null
    };

    var roleLabels = {
        super_admin: 'Super Admin',
        state_president: 'State President',
        district_president: 'District President',
        media_admin: 'Media Admin',
        complaint_admin: 'Complaint Admin'
    };

    var roleIcons = {
        super_admin: 'fa-shield-halved',
        state_president: 'fa-flag',
        district_president: 'fa-map-location-dot',
        media_admin: 'fa-photo-film',
        complaint_admin: 'fa-triangle-exclamation'
    };

    var $adminTableBody = $('#adminRoleTableBody');
    var $memberTableBody = $('#memberRoleTableBody');
    var adminFormModal = new bootstrap.Modal(document.getElementById('adminFormModal'));
    var memberFormEl = document.getElementById('memberFormModal');
    var memberFormModal = memberFormEl ? new bootstrap.Modal(memberFormEl) : null;
    var hasMemberUI = $memberTableBody.length > 0 && memberFormModal !== null;
    var logsModal = new bootstrap.Modal(document.getElementById('activityLogsModal'));
    var memberReloadTimer = null;

    var api = function (action, data, method) {
        var requestMethod = String(method || 'GET').toUpperCase();
        var payload;

        if (Array.isArray(data)) {
            payload = data.slice();
            payload.push({ name: 'action', value: action });
            if (requestMethod !== 'GET') {
                payload.push({ name: 'csrf_token', value: cfg.csrfToken });
            }
        } else {
            payload = data || {};
            payload.action = action;
            if (requestMethod !== 'GET') {
                payload.csrf_token = cfg.csrfToken;
            }
        }

        return $.ajax({
            url: cfg.endpoint,
            method: requestMethod,
            data: payload,
            dataType: 'json'
        });
    };

    var extractAjaxError = function (xhr, fallback) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }
        if (xhr && typeof xhr.responseText === 'string' && xhr.responseText.trim() !== '') {
            return xhr.responseText.slice(0, 180);
        }
        return fallback;
    };

    var toast = function (title, icon) {
        if (window.Swal) {
            window.Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon || 'success',
                title: title,
                showConfirmButton: false,
                timer: 2200
            });
            return;
        }
        alert(title);
    };

    var syncSelectedCounter = function () {
        $('#selectedCount').text(state.selected.size + ' selected');
    };

    var toSafe = function (value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    var formatRole = function (role) {
        var label = roleLabels[role] || role || 'Unknown';
        var icon = roleIcons[role] || 'fa-user-shield';
        return '<span class="role-badge ' + toSafe(role) + '"><i class="fa-solid ' + icon + '"></i>' + toSafe(label) + '</span>';
    };

    var formatMemberRole = function (role) {
        var label = String(role || '').trim() || 'Member';
        return '<span class="role-badge member-role-neutral"><i class="fa-solid fa-users"></i>' + toSafe(label) + '</span>';
    };

    var formatStatus = function (status) {
        var normalized = status === 'disabled' ? 'disabled' : 'active';
        var label = normalized === 'active' ? 'Active' : 'Disabled';
        return '<span class="status-pill ' + normalized + '">' + label + '</span>';
    };

    var formatPermissions = function (permissions) {
        if (!Array.isArray(permissions) || permissions.length === 0) {
            return '<span class="text-secondary small">No permissions</span>';
        }
        return permissions.slice(0, 4).map(function (item) {
            return '<span class="permission-chip">' + toSafe(item.replace('_', ' ')) + '</span>';
        }).join('') + (permissions.length > 4 ? '<span class="permission-chip">+' + (permissions.length - 4) + '</span>' : '');
    };

    var renderAdminTable = function () {
        if (!state.admins.length) {
            $adminTableBody.html('<tr><td colspan="12" class="text-center py-4 text-secondary">No admin accounts found.</td></tr>');
            syncSelectedCounter();
            return;
        }

        var rows = state.admins.map(function (admin) {
            var checked = state.selected.has(admin.id) ? 'checked' : '';
            var actions = [
                '<button class="btn btn-outline-primary btn-sm" data-action="edit" data-id="' + admin.id + '"><i class="fa-solid fa-pen"></i></button>',
                '<button class="btn btn-outline-info btn-sm" data-action="change-role" data-id="' + admin.id + '"><i class="fa-solid fa-user-gear"></i></button>',
                '<button class="btn btn-outline-warning btn-sm" data-action="reset-password" data-id="' + admin.id + '"><i class="fa-solid fa-key"></i></button>',
                '<button class="btn btn-outline-secondary btn-sm" data-action="suspend" data-id="' + admin.id + '"><i class="fa-solid fa-user-slash"></i></button>',
                '<button class="btn btn-outline-danger btn-sm" data-action="delete" data-id="' + admin.id + '"><i class="fa-solid fa-trash"></i></button>',
                '<button class="btn btn-outline-dark btn-sm" data-action="view-logs" data-id="' + admin.id + '"><i class="fa-solid fa-clock-rotate-left"></i></button>'
            ].join('');

            return '<tr>' +
                '<td><input type="checkbox" class="row-check" data-id="' + admin.id + '" ' + checked + '></td>' +
                '<td><strong>' + toSafe(admin.full_name || admin.username) + '</strong></td>' +
                '<td>' + toSafe(admin.username) + '</td>' +
                '<td>' + toSafe(admin.email || '-') + '</td>' +
                '<td>' + toSafe(admin.mobile || '-') + '</td>' +
                '<td>' + formatRole(admin.role) + '</td>' +
                '<td>' + toSafe(admin.state || '-') + '</td>' +
                '<td>' + toSafe(admin.district || '-') + '</td>' +
                '<td>' + formatPermissions(admin.permissions || []) + '</td>' +
                '<td>' + toSafe(admin.last_login || '-') + '</td>' +
                '<td>' + formatStatus(admin.status) + '</td>' +
                '<td><div class="action-btn-group">' + actions + '</div></td>' +
            '</tr>';
        });

        $adminTableBody.html(rows.join(''));
        syncSelectedCounter();
    };

    var renderAdminSummary = function (summary) {
        $('#sumTotalAdmins').text(summary.total_admins || 0);
        $('#sumSuperAdmins').text(summary.super_admins || 0);
        $('#sumStatePresidents').text(summary.state_presidents || 0);
        $('#sumDistrictPresidents').text(summary.district_presidents || 0);
        $('#sumActiveAdmins').text(summary.active_admins || 0);
    };

    var loadAdmins = function () {
        api('list', {
            search: $('#filterSearch').val() || '',
            role: $('#filterRole').val() || '',
            state: $('#filterState').val() || '',
            district: $('#filterDistrict').val() || '',
            status: $('#filterStatus').val() || ''
        }, 'GET').done(function (res) {
            if (!res || !res.ok) {
                toast((res && res.message) || 'Failed to load admins', 'error');
                return;
            }
            state.admins = Array.isArray(res.admins) ? res.admins : [];
            state.selected = new Set(Array.from(state.selected).filter(function (id) {
                return state.admins.some(function (admin) { return Number(admin.id) === Number(id); });
            }));
            renderAdminSummary(res.summary || {});
            renderAdminTable();
        }).fail(function (xhr) {
            toast(extractAjaxError(xhr, 'Unable to fetch role data'), 'error');
        });
    };

    var getAdminById = function (id) {
        return state.admins.find(function (item) { return Number(item.id) === Number(id); }) || null;
    };

    var setFormForAdmin = function (admin) {
        state.editingAdmin = admin || null;
        var $form = $('#adminForm');
        $form[0].reset();
        $form.find('input[name="permissions[]"]').prop('checked', false);

        if (admin) {
            $('#adminFormTitle').text('Edit Admin');
            $('#adminIdField').val(admin.id);
            $form.find('input[name="full_name"]').val(admin.full_name || '');
            $form.find('input[name="username"]').val(admin.username || '');
            $form.find('input[name="email"]').val(admin.email || '');
            $form.find('input[name="mobile"]').val(admin.mobile || '');
            $form.find('input[name="password"]').attr('placeholder', 'Leave blank to keep current password');
            $form.find('select[name="role"]').val(admin.role || 'super_admin');
            $form.find('input[name="state"]').val(admin.state || 'Andhra Pradesh');
            $form.find('input[name="district"]').val(admin.district || '');
            $form.find('select[name="status"]').val(admin.status || 'active');
            (admin.permissions || []).forEach(function (perm) {
                $form.find('input[name="permissions[]"][value="' + perm + '"]').prop('checked', true);
            });
        } else {
            $('#adminFormTitle').text('Add New Admin');
            $('#adminIdField').val('');
            $form.find('input[name="password"]').attr('placeholder', 'Required for new admin');
        }
    };

    var openLogs = function (adminId) {
        $('#activityLogsBody').html('<p class="text-secondary mb-0">Loading logs...</p>');
        logsModal.show();
        api('logs', { admin_id: adminId }, 'GET').done(function (res) {
            if (!res || !res.ok) {
                $('#activityLogsBody').html('<p class="text-danger mb-0">Unable to load logs.</p>');
                return;
            }
            var logs = Array.isArray(res.logs) ? res.logs : [];
            if (!logs.length) {
                $('#activityLogsBody').html('<p class="text-secondary mb-0">No logs found.</p>');
                return;
            }
            var html = logs.map(function (log) {
                return '<article class="log-item">' +
                    '<div><strong>' + toSafe(log.action) + '</strong> by ' + toSafe(log.actor) + '</div>' +
                    '<div class="small">Target: ' + toSafe(log.target) + '</div>' +
                    '<div class="small">' + toSafe(log.details || '-') + '</div>' +
                    '<div class="meta">' + toSafe(log.created_at || '-') + ' | IP: ' + toSafe(log.ip || '-') + '</div>' +
                '</article>';
            }).join('');
            $('#activityLogsBody').html(html);
        }).fail(function () {
            $('#activityLogsBody').html('<p class="text-danger mb-0">Unable to load logs.</p>');
        });
    };

    var memberSummaryFromState = function () {
        var uniqueRoles = {};
        var pendingCount = 0;
        var approvedCount = 0;

        state.members.forEach(function (member) {
            var memberRole = String(member.role || '').trim() || String(member.designation || '').trim() || 'Member';
            uniqueRoles[memberRole.toLowerCase()] = true;
            if (String(member.status || '').toLowerCase() === 'pending') {
                pendingCount += 1;
            }
            if (String(member.status || '').toLowerCase() === 'approved') {
                approvedCount += 1;
            }
        });

        $('#sumLoadedMembers').text(state.members.length);
        $('#sumUniqueMemberRoles').text(Object.keys(uniqueRoles).length);
        $('#sumPendingMembers').text(pendingCount);
        $('#sumApprovedMembers').text(approvedCount);
    };

    var renderMemberTable = function () {
        if (!state.members.length) {
            $memberTableBody.html('<tr><td colspan="8" class="text-center py-4 text-secondary">No members found for the current filters.</td></tr>');
            memberSummaryFromState();
            return;
        }

        var rows = state.members.map(function (member) {
            var memberName = String(member.name || member.full_name || '').trim() || '-';
            var memberRole = String(member.role || '').trim() || String(member.designation || '').trim() || 'Member';
            var phone = String(member.phone || member.mobile || '').trim() || '-';
            return '<tr>' +
                '<td><strong>' + toSafe(memberName) + '</strong></td>' +
                '<td>' + toSafe(member.member_id || '-') + '</td>' +
                '<td>' + toSafe(member.district || '-') + '</td>' +
                '<td>' + toSafe(member.designation || member.role || '-') + '</td>' +
                '<td>' + formatMemberRole(memberRole) + '</td>' +
                '<td>' + toSafe(phone) + '</td>' +
                '<td>' + formatStatus(member.status || 'pending') + '</td>' +
                '<td><div class="action-btn-group"><button class="btn btn-outline-primary btn-sm" data-action="edit-member-role" data-id="' + member.id + '"><i class="fa-solid fa-user-pen"></i></button></div></td>' +
            '</tr>';
        });

        $memberTableBody.html(rows.join(''));
        memberSummaryFromState();
    };

    var loadMembers = function () {
        if (!hasMemberUI) {
            return;
        }
        api('member_list', {
            search: $('#memberFilterSearch').val() || '',
            district: $('#memberFilterDistrict').val() || '',
            role: $('#memberFilterRole').val() || '',
            status: $('#memberFilterStatus').val() || ''
        }, 'GET').done(function (res) {
            if (!res || !res.ok) {
                toast((res && res.message) || 'Failed to load members', 'error');
                return;
            }
            state.members = Array.isArray(res.members) ? res.members : [];
            renderMemberTable();
        }).fail(function () {
            toast('Unable to fetch member data', 'error');
        });
    };

    var getMemberById = function (id) {
        return state.members.find(function (item) { return Number(item.id) === Number(id); }) || null;
    };

    var setFormForMember = function (member) {
        state.editingMember = member || null;
        var $form = $('#memberForm');
        if (!$form.length) {
            return;
        }
        $form[0].reset();

        if (!member) {
            $('#memberFormTitle').text('Update Member Role');
            return;
        }

        $('#memberFormTitle').text('Edit Member Role');
        $('#memberIdField').val(member.id);
        $('#memberNameField').val(member.name || member.full_name || '');
        $('#memberMemberIdField').val(member.member_id || '');
        $('#memberDistrictField').val(member.district || '');
        $('#memberDesignationField').val(member.designation || member.role || '');
        $('#memberRoleField').val(member.role || member.designation || '');
    };

    var scheduleMemberLoad = function () {
        if (!hasMemberUI) {
            return;
        }
        if (memberReloadTimer) {
            clearTimeout(memberReloadTimer);
        }
        memberReloadTimer = window.setTimeout(function () {
            loadMembers();
        }, 180);
    };

    $('#addAdminBtn').on('click', function () {
        setFormForAdmin(null);
        adminFormModal.show();
    });

    $('#refreshMembersBtn').on('click', function () {
        loadMembers();
    });

    $('#adminRoleTableBody').on('change', '.row-check', function () {
        var id = Number($(this).data('id'));
        if ($(this).is(':checked')) {
            state.selected.add(id);
        } else {
            state.selected.delete(id);
        }
        syncSelectedCounter();
    });

    $('#checkAllAdmins').on('change', function () {
        var checked = $(this).is(':checked');
        $('.row-check').prop('checked', checked).trigger('change');
    });

    $('#bulkAction').on('change', function () {
        var action = $(this).val();
        $('#bulkRole').toggle(action === 'change_role');
    });

    $('#applyBulkBtn').on('click', function () {
        var action = String($('#bulkAction').val() || '');
        var ids = Array.from(state.selected);
        if (!action) {
            toast('Select a bulk action', 'warning');
            return;
        }
        if (!ids.length) {
            toast('Select at least one admin', 'warning');
            return;
        }

        api('bulk_action', {
            selected_ids: ids,
            bulk_action: action,
            new_role: $('#bulkRole').val() || ''
        }, 'POST').done(function (res) {
            if (!res || !res.ok) {
                toast((res && res.message) || 'Bulk action failed', 'error');
                return;
            }
            state.selected.clear();
            $('#checkAllAdmins').prop('checked', false);
            toast(res.message || 'Bulk action completed', 'success');
            loadAdmins();
        }).fail(function () {
            toast('Bulk action failed', 'error');
        });
    });

    $('#clearFiltersBtn').on('click', function () {
        $('#filterSearch').val('');
        $('#filterRole').val('');
        $('#filterState').val('');
        $('#filterDistrict').val('');
        $('#filterStatus').val('');
        loadAdmins();
    });

    $('#filterSearch, #filterState, #filterDistrict').on('input', function () {
        loadAdmins();
    });

    $('#filterRole, #filterStatus').on('change', function () {
        loadAdmins();
    });

    $('#adminRoleTableBody').on('click', 'button[data-action]', function () {
        var action = String($(this).data('action') || '');
        var adminId = Number($(this).data('id'));
        var admin = getAdminById(adminId);
        if (!admin) {
            return;
        }

        if (action === 'edit' || action === 'change-role') {
            setFormForAdmin(admin);
            adminFormModal.show();
            return;
        }

        if (action === 'view-logs') {
            openLogs(adminId);
            return;
        }

        if (action === 'reset-password') {
            var pwd = prompt('Enter new password (min 6 chars):');
            if (!pwd) {
                return;
            }
            api('reset_password', { admin_id: adminId, new_password: pwd }, 'POST').done(function (res) {
                if (!res || !res.ok) {
                    toast((res && res.message) || 'Password reset failed', 'error');
                    return;
                }
                toast(res.message || 'Password updated', 'success');
            }).fail(function () {
                toast('Password reset failed', 'error');
            });
            return;
        }

        if (action === 'suspend') {
            var status = admin.status === 'active' ? 'disabled' : 'active';
            api('set_status', { admin_id: adminId, status: status }, 'POST').done(function (res) {
                if (!res || !res.ok) {
                    toast((res && res.message) || 'Status update failed', 'error');
                    return;
                }
                toast(res.message || 'Status updated', 'success');
                loadAdmins();
            }).fail(function () {
                toast('Status update failed', 'error');
            });
            return;
        }

        if (action === 'delete') {
            if (!confirm('Delete this admin account?')) {
                return;
            }
            api('delete_admin', { admin_id: adminId }, 'POST').done(function (res) {
                if (!res || !res.ok) {
                    toast((res && res.message) || 'Delete failed', 'error');
                    return;
                }
                toast(res.message || 'Admin deleted', 'success');
                loadAdmins();
            }).fail(function () {
                toast('Delete failed', 'error');
            });
        }
    });

    $('#formRole').on('change', function () {
        var role = String($(this).val() || '');
        if (role === 'district_president') {
            $('#formDistrict').attr('required', 'required');
        } else {
            $('#formDistrict').removeAttr('required');
            $('#formDistrict').val('');
        }
    });

    $('#adminForm').on('submit', function (event) {
        event.preventDefault();
        var data = $(this).serializeArray();
        api('save_admin', data, 'POST').done(function (res) {
            if (!res || !res.ok) {
                toast((res && res.message) || 'Save failed', 'error');
                return;
            }
            toast(res.message || 'Admin saved', 'success');
            adminFormModal.hide();
            loadAdmins();
        }).fail(function (xhr) {
            toast(extractAjaxError(xhr, 'Save failed'), 'error');
        });
    });

    $('#memberRoleTableBody').on('click', 'button[data-action="edit-member-role"]', function () {
        if (!hasMemberUI || !memberFormModal) {
            return;
        }
        var memberId = Number($(this).data('id'));
        var member = getMemberById(memberId);
        if (!member) {
            return;
        }
        setFormForMember(member);
        memberFormModal.show();
    });

    $('#memberForm').on('submit', function (event) {
        event.preventDefault();
        var data = $(this).serializeArray();
        api('save_member_role', data, 'POST').done(function (res) {
            if (!res || !res.ok) {
                toast((res && res.message) || 'Member update failed', 'error');
                return;
            }
            toast(res.message || 'Member role saved', 'success');
            memberFormModal.hide();
            loadMembers();
        }).fail(function () {
            toast('Member update failed', 'error');
        });
    });

    $('#memberFilterSearch, #memberFilterDistrict, #memberFilterRole').on('input', function () {
        scheduleMemberLoad();
    });

    $('#memberFilterStatus').on('change', function () {
        loadMembers();
    });

    $('#clearMemberFiltersBtn').on('click', function () {
        $('#memberFilterSearch').val('');
        $('#memberFilterDistrict').val('');
        $('#memberFilterRole').val('');
        $('#memberFilterStatus').val('');
        loadMembers();
    });

    loadAdmins();
    if (hasMemberUI) {
        loadMembers();
    }
})();
