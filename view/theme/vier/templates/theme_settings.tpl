{{*
  * Copyright (C) 2010-2024, the Friendica project
  * SPDX-FileCopyrightText: 2010-2024 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}

{{include file="field_select.tpl" field=$style}}
{{include file="field_select.tpl" field=$show_pages}}
{{include file="field_select.tpl" field=$show_profiles}}
{{include file="field_select.tpl" field=$show_helpers}}
{{include file="field_select.tpl" field=$show_services}}
{{include file="field_select.tpl" field=$show_friends}}
{{include file="field_select.tpl" field=$show_lastusers}}

<div class="settings-submit-wrapper">
	<input type="submit" value="{{$submit}}" class="settings-submit" name="vier-settings-submit" />
</div>

