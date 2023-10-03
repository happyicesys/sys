import{_ as A}from"./Authenticated.28390677.js";import{_ as p}from"./Button.970da128.js";import{_ as E}from"./Paginator.ecee422f.js";import{_ as R,r as Z}from"./SearchInput.e49b7c52.js";import{_ as B}from"./MultiSelect.3cb2fcdc.js";import{_ as V,a as g}from"./TableData.9fc636b2.js";import{_ as S}from"./TableHeadSort.e2824b9c.js";import{g as d,h as q,f as v,a as s,u as c,w as o,F as L,o as u,Z as z,b as e,d as i,t as m,k as G,l as k,c as I,O as w,R as H}from"./app.ec18ef6a.js";import{r as J}from"./PlusIcon.3311e1f3.js";import{r as Q}from"./BackspaceIcon.2c7b16fb.js";import{r as W}from"./PencilSquareIcon.37ab6809.js";import{r as X}from"./TrashIcon.4d7d7f1a.js";import"./open-closed.21ff182f.js";import"./use-resolve-button-type.5c16f99c.js";import"./RectangleStackIcon.8d6c2b45.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.56960726.js";const Y=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Cashless Terminal) ",-1),ee={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},le=e("span",null," Create ",-1),ae={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},oe=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Provider ",-1),ne={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},re={class:"mt-3"},ie={class:"flex space-x-1"},de=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),ue={class:"flex flex-col space-y-2"},me={class:"text-sm text-gray-700 leading-5 flex space-x-1"},fe=e("span",null,"Showing",-1),he={class:"font-medium"},pe=e("span",null,"to",-1),ge={class:"font-medium"},ve=e("span",null,"of",-1),_e={class:"font-medium"},xe=e("span",null,"results",-1),ye={class:"mt-6 flex flex-col"},be={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},we={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ce={class:"bg-gray-100"},Pe={class:"divide-x divide-gray-200"},Te={class:"bg-white"},$e={class:"flex justify-center space-x-1"},Be=e("span",null," Edit ",-1),Ve=e("span",null," Delete ",-1),Se={key:0},Le=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ie=[Le],Je={__name:"Index",props:{cashlessProviders:Object,cashlessTerminals:Object},setup(n){const K=n,t=d({code:"",phone_number:"",telco_id:"",sortKey:"",sortBy:!0,numberPerPage:100}),f=d(!1),_=d(),x=d(""),y=d([]),C=d([]);q(()=>{y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],t.value.numberPerPage=y.value[0],C.value=K.cashlessProviders.data.map(r=>({id:r.id,name:r.name}))});function D(){x.value="create",_.value=null,f.value=!0}function F(r){!confirm("Are you sure to delete "+r.name+"?")||w.delete("/cashless-terminals/"+r.id)}function N(r){x.value="update",_.value=r,f.value=!0}function b(){w.get("/cashless-terminals",{...t.value,telco_id:t.value.telco_id.id,numberPerPage:t.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){w.get("/cashless-terminals")}function P(r){t.value.sortKey=r,t.value.sortBy=!t.value.sortBy,b()}function O(){f.value=!1}return(r,l)=>{const j=H("Form");return u(),v(L,null,[s(c(z),{title:"CashlessTerminal"}),s(A,null,{header:o(()=>[Y]),default:o(()=>{var T,$;return[e("div",ee,[e("div",se,[e("div",te,[s(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[0]||(l[0]=a=>D())},{default:o(()=>[s(c(J),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})]),e("div",ae,[s(R,{placeholderStr:"Terminal ID",modelValue:t.value.code,"onUpdate:modelValue":l[1]||(l[1]=a=>t.value.code=a)},{default:o(()=>[i(" Terminal ID ")]),_:1},8,["modelValue"]),e("div",null,[oe,s(B,{modelValue:t.value.cashless_provider_id,"onUpdate:modelValue":l[2]||(l[2]=a=>t.value.cashless_provider_id=a),options:C.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",ne,[e("div",re,[e("div",ie,[s(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[3]||(l[3]=a=>b())},{default:o(()=>[s(c(Z),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1}),s(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[4]||(l[4]=a=>M())},{default:o(()=>[s(c(Q),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",ue,[e("p",me,[fe,e("span",he,m((T=n.cashlessTerminals.meta.from)!=null?T:0),1),pe,e("span",ge,m(($=n.cashlessTerminals.meta.to)!=null?$:0),1),ve,e("span",_e,m(n.cashlessTerminals.meta.total),1),xe]),s(B,{modelValue:t.value.numberPerPage,"onUpdate:modelValue":l[5]||(l[5]=a=>t.value.numberPerPage=a),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",ye,[e("div",be,[e("div",ke,[e("table",we,[e("thead",Ce,[e("tr",Pe,[s(V,null,{default:o(()=>[i(" # ")]),_:1}),s(S,{modelName:"code",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:l[6]||(l[6]=a=>P("code"))},{default:o(()=>[i(" Terminal ID ")]),_:1},8,["sortKey","sortBy"]),s(S,{modelName:"cashless_provider_id",sortKey:t.value.sortKey,sortBy:t.value.sortBy,onSortTable:l[7]||(l[7]=a=>P("cashless_provider_id"))},{default:o(()=>[i(" Provider ")]),_:1},8,["sortKey","sortBy"]),s(V)])]),e("tbody",Te,[(u(!0),v(L,null,G(n.cashlessTerminals.data,(a,h)=>(u(),v("tr",{key:a.id,class:"divide-x divide-gray-200"},[s(g,{currentIndex:h,totalLength:n.cashlessTerminals.length,inputClass:"text-center"},{default:o(()=>[i(m(n.cashlessTerminals.meta.from+h),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:h,totalLength:n.cashlessTerminals.length,inputClass:"text-left"},{default:o(()=>[i(m(a.code),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:h,totalLength:n.cashlessTerminals.length,inputClass:"text-left"},{default:o(()=>[i(m(a.cashlessProvider.name),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:h,totalLength:n.cashlessTerminals.length,inputClass:"text-center"},{default:o(()=>[e("div",$e,[s(p,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:U=>N(a)},{default:o(()=>[s(c(W),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"]),s(p,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:U=>F(a)},{default:o(()=>[s(c(X),{class:"w-4 h-4"}),Ve]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.cashlessTerminals.data.length?k("",!0):(u(),v("tr",Se,Ie))])]),n.cashlessTerminals.data.length?(u(),I(E,{key:0,links:n.cashlessTerminals.links,meta:n.cashlessTerminals.meta},null,8,["links","meta"])):k("",!0)])])])]),f.value?(u(),I(j,{key:0,cashlessTerminal:_.value,telcos:r.telcos,type:x.value,showModal:f.value,onModalClose:O},null,8,["cashlessTerminal","telcos","type","showModal"])):k("",!0)]}),_:1})],64)}}};export{Je as default};