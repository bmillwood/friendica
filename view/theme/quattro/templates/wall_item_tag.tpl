{{*
  * Copyright (C) 2010-2024, the Friendica project
  * SPDX-FileCopyrightText: 2010-2024 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}
{{if $mode == display}}
{{else}}
{{if $item.comment_firstcollapsed}}
	<div class="hide-comments-outer">
		<span id="hide-comments-total-{{$item.id}}"
			class="hide-comments-total">{{$item.num_comments}}</span>
			<span id="hide-comments-{{$item.id}}"
				class="hide-comments fakelink"
				onclick="showHideComments({{$item.id}});">{{$item.hide_text}}</span>
			{{if $item.thread_level==3}} -
			<span id="hide-thread-{{$item}}-id"
				class="fakelink"
				onclick="showThread({{$item.id}});">expand</span> /
			<span id="hide-thread-{{$item}}-id"
				class="fakelink"
				onclick="hideThread({{$item.id}});">collapse</span> thread{{/if}}
	</div>
	<div id="collapsed-comments-{{$item.id}}" class="collapsed-comments" style="display: none;">
{{/if}}
{{/if}}

{{if $item.thread_level!=1}}<div class="children">{{/if}}


<div class="wall-item-container item-tag {{$item.indent}} {{$item.shiny}}">
	<div class="wall-item-item">
		<div class="wall-item-info">
			<div class="contact-photo-wrapper">
				<a href="{{$item.profile_url}}" target="redir" title="{{$item.linktitle}}" class="contact-photo-link" id="wall-item-photo-link-{{$item.id}}">
					<img src="{{$item.thumb}}" class="contact-photo{{$item.sparkle}}" id="wall-item-photo-{{$item.id}}" alt="{{$item.name}}" />
				</a>
				<ul class="contact-menu menu-popup" id="wall-item-photo-menu-{{$item.id}}">
				{{$item.item_photo_menu_html nofilter}}
				</ul>

			</div>
			<div class="wall-item-location">{{$item.location_html nofilter}}</div>
		</div>
		<div class="wall-item-content">
			{{$item.ago}} {{$item.body_html nofilter}}
		</div>
			<div class="wall-item-tools">
				{{if $item.drop && $item.drop.pagedrop}}
					<input type="checkbox" title="{{$item.drop.select}}" name="itemselected[]" class="item-select" value="{{$item.id}}" />
				{{/if}}
				{{if $item.drop && $item.drop.dropping}}
					<a href="item/drop/{{$item.id}}" onclick="return confirmDelete();" class="icon delete s16" title="{{$item.drop.label}}">{{$item.drop.label}}</a>
				{{/if}}
			</div>
	</div>
</div>

{{if $item.thread_level!=1}}</div>{{/if}}

{{if $mode == display}}
{{else}}
{{if $item.comment_lastcollapsed}}</div>{{/if}}
{{/if}}

{{* top thread comment box *}}
{{if $item.threaded}}{{if $item.comment_html}}{{if $item.thread_level==1}}
<div class="wall-item-comment-wrapper">{{$item.comment_html nofilter}}</div>
{{/if}}{{/if}}{{/if}}

{{if $item.flatten}}
<div class="wall-item-comment-wrapper">{{$item.comment_html nofilter}}</div>
{{/if}}
