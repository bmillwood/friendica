{{*
  * Copyright (C) 2010-2024, the Friendica project
  * SPDX-FileCopyrightText: 2010-2024 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}
<div id="adminpage">
	<h1>{{$title}} - {{$page}}</h1>
	<p>{{$intro1}}</p>
	<p>{{$intro2}}</p>

	<form action="{{$baseurl}}/moderation/item/delete" method="post">
		<input type="hidden" name="form_security_token" value="{{$form_security_token}}">
		{{include file="field_input.tpl" field=$deleteitemguid}}
		<div class="submit"><input type="submit" name="page_deleteitem_submit" value="{{$submit}}" /></div>
	</form>

</div>
