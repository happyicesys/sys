import{_ as K}from"./Authenticated.5914d038.js";import{_ as u}from"./Button.1651e07c.js";import D from"./Form.5034a506.js";import{_ as A,r as E,a as H,T as P,b as U,c as O,d as b}from"./TableHeadSort.8d0fc293.js";import{_ as R}from"./MultiSelect.855de355.js";import{i as m,j as T,o as r,g as x,a as t,b as d,w as o,F as B,H as J,d as e,t as f,m as q,p as w,c as S,f as p,J as k}from"./app.278c15b3.js";import{r as z,a as G}from"./PlusIcon.39b65606.js";import{r as Q}from"./TrashIcon.2a358ea2.js";import"./open-closed.ce85e457.js";import"./use-resolve-button-type.d09d7927.js";import"./RectangleStackIcon.6fcdc1d3.js";import"./FormInput.33a2053d.js";import"./Modal.3cc5632f.js";import"./ArrowUturnLeftIcon.5dbf3f99.js";import"./CheckCircleIcon.e6f7bb72.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.8a461f2f.js";const W=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Permissions) ",-1),X={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se=p(" Name "),oe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ne={class:"mt-3"},ae={class:"flex space-x-1"},le=e("span",null," Search ",-1),ie=e("span",null," Reset ",-1),re={class:"flex flex-col space-y-2"},de={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ce=e("span",null,"Showing",-1),ue={class:"font-medium"},me=e("span",null,"to",-1),fe={class:"font-medium"},pe=e("span",null,"of",-1),ge={class:"font-medium"},xe=e("span",null,"results",-1),_e={class:"mt-6 flex flex-col"},he={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ve={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ye={class:"min-w-full border-separate",style:{"border-spacing":"0"}},be={class:"bg-gray-100"},we={class:"divide-x divide-gray-200"},ke=p(" # "),Ce=p(" Name "),$e={class:"bg-white"},Pe={class:"flex justify-center space-x-1"},Be=e("span",null," Edit ",-1),Se=e("span",null," Delete ",-1),Ve={key:0},Ne=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ie=[Ne],Qe={__name:"Index",props:{permissions:Object},setup(n){const a=m({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),c=m(!1),_=m(),h=m(""),v=m([]);T(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=v.value[0]});function V(){h.value="create",_.value=null,c.value=!0}function N(i){!confirm("Are you sure to delete "+i.name+"?")||k.Inertia.delete("/permissions/"+i.id)}function I(i){h.value="update",_.value=i,c.value=!0}function y(){k.Inertia.get("/permissions",{...a.value,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function L(){k.Inertia.get("/permissions")}function M(i){a.value.sortKey=i,a.value.sortBy=!a.value.sortBy,y()}function j(){c.value=!1}return(i,s)=>(r(),x(B,null,[t(d(J),{title:"Permissions"}),t(K,null,{header:o(()=>[W]),default:o(()=>{var C,$;return[e("div",X,[e("div",Y,[e("div",Z,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=l=>V())},{default:o(()=>[t(d(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(A,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":s[1]||(s[1]=l=>a.value.name=l)},{default:o(()=>[se]),_:1},8,["modelValue"])]),e("div",oe,[e("div",ne,[e("div",ae,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=l=>y())},{default:o(()=>[t(d(E),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=l=>L())},{default:o(()=>[t(d(H),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1})])]),e("div",re,[e("p",de,[ce,e("span",ue,f((C=n.permissions.meta.from)!=null?C:0),1),me,e("span",fe,f(($=n.permissions.meta.to)!=null?$:0),1),pe,e("span",ge,f(n.permissions.meta.total),1),xe]),t(R,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=l=>a.value.numberPerPage=l),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",_e,[e("div",he,[e("div",ve,[e("table",ye,[e("thead",be,[e("tr",we,[t(P,null,{default:o(()=>[ke]),_:1}),t(U,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[5]||(s[5]=l=>M("name"))},{default:o(()=>[Ce]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",$e,[(r(!0),x(B,null,q(n.permissions.data,(l,g)=>(r(),x("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:g,totalLength:n.permissions.length,inputClass:"text-center"},{default:o(()=>[p(f(n.permissions.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:n.permissions.length,inputClass:"text-left"},{default:o(()=>[p(f(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:n.permissions.length,inputClass:"text-center"},{default:o(()=>[e("div",Pe,[t(u,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>I(l)},{default:o(()=>[t(d(G),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"]),t(u,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>N(l)},{default:o(()=>[t(d(Q),{class:"w-4 h-4"}),Se]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.permissions.data.length?w("",!0):(r(),x("tr",Ve,Ie))])]),n.permissions.data.length?(r(),S(O,{key:0,links:n.permissions.links,meta:n.permissions.meta},null,8,["links","meta"])):w("",!0)])])])]),c.value?(r(),S(D,{key:0,permission:_.value,type:h.value,showModal:c.value,onModalClose:j},null,8,["permission","type","showModal"])):w("",!0)]}),_:1})],64))}};export{Qe as default};