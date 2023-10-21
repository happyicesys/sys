import{_ as D}from"./Authenticated.51f8df2d.js";import{_ as u}from"./Button.0b5b1d9a.js";import I from"./Form.74f9e546.js";import{_ as O}from"./Paginator.0d7fb970.js";import{_ as A,r as E}from"./SearchInput.8f48840f.js";import{_ as T}from"./MultiSelect.48ff9607.js";import{_ as $,a as b}from"./TableData.885e60f5.js";import{_ as U}from"./TableHeadSort.04d09add.js";import{g as m,h as R,f as h,a as t,u as i,w as o,F as B,o as d,Z,b as e,d as f,t as p,k as q,l as P,c as S,O as w}from"./app.8dfb483a.js";import{r as z}from"./PlusIcon.50a86d1a.js";import{r as G}from"./BackspaceIcon.1cc84e35.js";import{r as H}from"./PencilSquareIcon.38a72125.js";import{r as J}from"./TrashIcon.c87df023.js";import"./open-closed.cdb7e47f.js";import"./use-resolve-button-type.39e09ef6.js";import"./RectangleStackIcon.8d6abf1b.js";import"./FormInput.a614dbc4.js";import"./Modal.c3620c5c.js";import"./disposables.1ad74aa0.js";import"./ArrowUturnLeftIcon.41207353.js";import"./CheckCircleIcon.42962ec5.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.946a0653.js";const Q=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Cashless Provider) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Y={class:"flex justify-end"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},se={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},oe={class:"mt-3"},ae={class:"flex space-x-1"},le=e("span",null," Search ",-1),ne=e("span",null," Reset ",-1),re={class:"flex flex-col space-y-2"},ie={class:"text-sm text-gray-700 leading-5 flex space-x-1"},de=e("span",null,"Showing",-1),ce={class:"font-medium"},ue=e("span",null,"to",-1),me={class:"font-medium"},fe=e("span",null,"of",-1),pe={class:"font-medium"},ge=e("span",null,"results",-1),he={class:"mt-6 flex flex-col"},ve={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},xe={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},_e={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ye={class:"bg-gray-100"},be={class:"divide-x divide-gray-200"},Pe={class:"bg-white"},we={class:"flex justify-center space-x-1"},ke=e("span",null," Edit ",-1),Ce=e("span",null," Delete ",-1),$e={key:0},Be=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Se=[Be],We={__name:"Index",props:{cashlessProviders:Object},setup(a){const l=m({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),c=m(!1),v=m(),x=m(""),_=m([]);R(()=>{_.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],l.value.numberPerPage=_.value[0]});function V(){x.value="create",v.value=null,c.value=!0}function N(r){!confirm("Are you sure to delete "+r.name+"?")||w.delete("/cashless-providers/"+r.id)}function L(r){x.value="update",v.value=r,c.value=!0}function y(){w.get("/cashless-providers",{...l.value,numberPerPage:l.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){w.get("/cashless-providers")}function F(r){l.value.sortKey=r,l.value.sortBy=!l.value.sortBy,y()}function K(){c.value=!1}return(r,s)=>(d(),h(B,null,[t(i(Z),{title:"Cashless Provider"}),t(D,null,{header:o(()=>[Q]),default:o(()=>{var k,C;return[e("div",W,[e("div",X,[e("div",Y,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=n=>V())},{default:o(()=>[t(i(z),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(A,{placeholderStr:"Name",modelValue:l.value.name,"onUpdate:modelValue":s[1]||(s[1]=n=>l.value.name=n)},{default:o(()=>[f(" Name ")]),_:1},8,["modelValue"])]),e("div",se,[e("div",oe,[e("div",ae,[t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[2]||(s[2]=n=>y())},{default:o(()=>[t(i(E),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1}),t(u,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=n=>M())},{default:o(()=>[t(i(G),{class:"h-4 w-4","aria-hidden":"true"}),ne]),_:1})])]),e("div",re,[e("p",ie,[de,e("span",ce,p((k=a.cashlessProviders.meta.from)!=null?k:0),1),ue,e("span",me,p((C=a.cashlessProviders.meta.to)!=null?C:0),1),fe,e("span",pe,p(a.cashlessProviders.meta.total),1),ge]),t(T,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":s[4]||(s[4]=n=>l.value.numberPerPage=n),options:_.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",he,[e("div",ve,[e("div",xe,[e("table",_e,[e("thead",ye,[e("tr",be,[t($,null,{default:o(()=>[f(" # ")]),_:1}),t(U,{modelName:"name",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:s[5]||(s[5]=n=>F("name"))},{default:o(()=>[f(" Name ")]),_:1},8,["sortKey","sortBy"]),t($)])]),e("tbody",Pe,[(d(!0),h(B,null,q(a.cashlessProviders.data,(n,g)=>(d(),h("tr",{key:n.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:g,totalLength:a.cashlessProviders.length,inputClass:"text-center"},{default:o(()=>[f(p(a.cashlessProviders.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:a.cashlessProviders.length,inputClass:"text-left"},{default:o(()=>[f(p(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:a.cashlessProviders.length,inputClass:"text-center"},{default:o(()=>[e("div",we,[t(u,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:j=>L(n)},{default:o(()=>[t(i(H),{class:"w-4 h-4"}),ke]),_:2},1032,["onClick"]),t(u,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:j=>N(n)},{default:o(()=>[t(i(J),{class:"w-4 h-4"}),Ce]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.cashlessProviders.data.length?P("",!0):(d(),h("tr",$e,Se))])]),a.cashlessProviders.data.length?(d(),S(O,{key:0,links:a.cashlessProviders.links,meta:a.cashlessProviders.meta},null,8,["links","meta"])):P("",!0)])])])]),c.value?(d(),S(I,{key:0,cashlessProvider:v.value,type:x.value,showModal:c.value,onModalClose:K},null,8,["cashlessProvider","type","showModal"])):P("",!0)]}),_:1})],64))}};export{We as default};
