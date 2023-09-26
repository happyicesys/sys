import{_ as U}from"./Authenticated.9fe60fc0.js";import{_}from"./Button.33536ddb.js";import A from"./Form.b497a84f.js";import{_ as E}from"./Paginator.af059acc.js";import{_ as R,r as Z}from"./SearchInput.480cabcb.js";import{_ as L}from"./MultiSelect.35807366.js";import{_ as k,a as h}from"./TableData.d3c4c59d.js";import{_ as q}from"./TableHeadSort.4a0cb10b.js";import{g as c,h as z,f as m,a as t,u as f,w as n,F as C,o as i,Z as G,b as e,d as g,t as d,k as N,l as $,c as M,O as P}from"./app.024e39e5.js";import{r as H}from"./PlusIcon.6cee21cd.js";import{r as J}from"./BackspaceIcon.f40c1c84.js";import{r as Q}from"./PencilSquareIcon.991cf1bc.js";import{r as W}from"./TrashIcon.378da092.js";import"./open-closed.4c597dc4.js";import"./use-resolve-button-type.0e6f995d.js";import"./RectangleStackIcon.7a8167e6.js";import"./FormInput.a4bc002f.js";import"./Modal.e83c5b6a.js";import"./platform.ff812502.js";import"./ArrowUturnLeftIcon.00eea8ad.js";import"./CheckCircleIcon.71ce7f83.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.18709a47.js";const X=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Tags) ",-1),Y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ae={class:"col-span-5 md:col-span-1"},ne=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customers ",-1),le={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},re={class:"mt-3"},ie={class:"flex space-x-1"},de=e("span",null," Search ",-1),ue=e("span",null," Reset ",-1),ce={class:"flex flex-col space-y-2"},me={class:"text-sm text-gray-700 leading-5 flex space-x-1"},fe=e("span",null,"Showing",-1),ge={class:"font-medium"},pe=e("span",null,"to",-1),xe={class:"font-medium"},_e=e("span",null,"of",-1),he={class:"font-medium"},ve=e("span",null,"results",-1),ye={class:"mt-6 flex flex-col"},be={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},we={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ke={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ce={class:"bg-gray-100"},$e={class:"divide-x divide-gray-200"},Pe={class:"bg-white"},Ve={class:"inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border bg-gray-200"},Be={class:"flex justify-center space-x-1"},Se=e("span",null," Edit ",-1),Le=e("span",null," Delete ",-1),Ne={key:0},Me=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Oe=[Me],st={__name:"Index",props:{customers:Object,tags:Object},setup(l){const O=l,s=c({name:"",customers:[],sortKey:"",sortBy:!0,numberPerPage:100}),p=c(!1),v=c(),y=c(""),b=c([]),V=c([]);z(()=>{b.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=b.value[0],V.value=O.customers.data.map(r=>({id:r.id,code:r.code,name:r.name,is_active:r.is_active}))});function j(){y.value="create",v.value=null,p.value=!0}function F(r){!confirm("Are you sure to delete "+r.name+"?")||P.delete("/tags/"+r.id)}function I(r){y.value="update",v.value=r,p.value=!0}function w(){P.get("/tags",{...s.value,customers:s.value.customers.map(r=>r.id),numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function K(){P.get("/tags")}function T(r){s.value.sortKey=r,s.value.sortBy=!s.value.sortBy,w()}function D(){p.value=!1}return(r,o)=>(i(),m(C,null,[t(f(G),{title:"Tags"}),t(U,null,{header:n(()=>[X]),default:n(()=>{var B,S;return[e("div",Y,[e("div",ee,[e("div",te,[t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=a=>j())},{default:n(()=>[t(f(H),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})]),e("div",oe,[t(R,{placeholderStr:"Name",modelValue:s.value.name,"onUpdate:modelValue":o[1]||(o[1]=a=>s.value.name=a)},{default:n(()=>[g(" Name ")]),_:1},8,["modelValue"]),e("div",ae,[ne,t(L,{modelValue:s.value.customers,"onUpdate:modelValue":o[2]||(o[2]=a=>s.value.customers=a),options:V.value,valueProp:"id",label:"code",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",le,[e("div",re,[e("div",ie,[t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[3]||(o[3]=a=>w())},{default:n(()=>[t(f(Z),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1}),t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[4]||(o[4]=a=>K())},{default:n(()=>[t(f(J),{class:"h-4 w-4","aria-hidden":"true"}),ue]),_:1})])]),e("div",ce,[e("p",me,[fe,e("span",ge,d((B=l.tags.meta.from)!=null?B:0),1),pe,e("span",xe,d((S=l.tags.meta.to)!=null?S:0),1),_e,e("span",he,d(l.tags.meta.total),1),ve]),t(L,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":o[5]||(o[5]=a=>s.value.numberPerPage=a),options:b.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:w},null,8,["modelValue","options"])])])]),e("div",ye,[e("div",be,[e("div",we,[e("table",ke,[e("thead",Ce,[e("tr",$e,[t(k,null,{default:n(()=>[g(" # ")]),_:1}),t(q,{modelName:"name",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:o[6]||(o[6]=a=>T("name"))},{default:n(()=>[g(" Name ")]),_:1},8,["sortKey","sortBy"]),t(k,null,{default:n(()=>[g(" Customers ")]),_:1}),t(k)])]),e("tbody",Pe,[(i(!0),m(C,null,N(l.tags.data,(a,x)=>(i(),m("tr",{key:a.id,class:"divide-x divide-gray-200"},[t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-center"},{default:n(()=>[g(d(l.tags.meta.from+x),1)]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-left"},{default:n(()=>[g(d(a.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-left"},{default:n(()=>[(i(!0),m(C,null,N(a.tagBindings.filter(function(u){return u.is_active}),u=>(i(),m("span",Ve,d(u.code)+", "+d(u.name),1))),256))]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-center"},{default:n(()=>[e("div",Be,[t(_,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:u=>I(a)},{default:n(()=>[t(f(Q),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"]),t(_,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:u=>F(a)},{default:n(()=>[t(f(W),{class:"w-4 h-4"}),Le]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.tags.data.length?$("",!0):(i(),m("tr",Ne,Oe))])]),l.tags.data.length?(i(),M(E,{key:0,links:l.tags.links,meta:l.tags.meta},null,8,["links","meta"])):$("",!0)])])])]),p.value?(i(),M(A,{key:0,tag:v.value,type:y.value,showModal:p.value,onModalClose:D},null,8,["tag","type","showModal"])):$("",!0)]}),_:1})],64))}};export{st as default};
